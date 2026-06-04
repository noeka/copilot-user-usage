<?php

namespace App\Services;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use Illuminate\Support\Facades\DB;

class UsageReportService
{
    /**
     * Aggregate summary totals for a period.
     * Pass $user = null for org-wide totals (admin only).
     */
    public function summary(?CopilotUser $user, Period $period): array
    {
        [$from, $to] = $period->dateRange();

        $query = DailyUsage::query()
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()]);

        if ($user !== null) {
            $query->where('copilot_user_id', $user->id);
        }

        $row = $query->selectRaw(
            'SUM(code_suggestions) as suggestions,
             SUM(code_acceptances) as acceptances,
             SUM(lines_suggested)  as lines_suggested,
             SUM(lines_accepted)   as lines_accepted,
             SUM(chat_interactions) as chat,
             COUNT(DISTINCT usage_date) as active_days,
             COUNT(DISTINCT copilot_user_id) as active_users'
        )->first();

        $suggestions = (int) ($row->suggestions ?? 0);
        $acceptances = (int) ($row->acceptances ?? 0);

        return [
            'code_suggestions'  => $suggestions,
            'code_acceptances'  => $acceptances,
            'acceptance_rate'   => $suggestions > 0 ? round($acceptances / $suggestions * 100, 1) : 0.0,
            'lines_suggested'   => (int) ($row->lines_suggested ?? 0),
            'lines_accepted'    => (int) ($row->lines_accepted ?? 0),
            'chat_interactions' => (int) ($row->chat ?? 0),
            'active_days'       => (int) ($row->active_days ?? 0),
            'active_users'      => (int) ($row->active_users ?? 0),
        ];
    }

    /**
     * Time-series data for a line chart (suggestions, acceptances, chat per bucket).
     */
    public function timeSeries(?CopilotUser $user, Period $period): array
    {
        [$from, $to] = $period->dateRange();

        $query = DailyUsage::query()
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()]);

        if ($user !== null) {
            $query->where('copilot_user_id', $user->id);
        }

        $rows = $query
            ->orderBy('usage_date')
            ->get(['usage_date', 'code_suggestions', 'code_acceptances', 'lines_accepted', 'chat_interactions']);

        // Bucket in PHP so this works on any database (MySQL/Postgres/SQLite).
        $buckets = [];
        foreach ($rows as $row) {
            $key = $period->bucketKey($row->usage_date);

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'label'          => $period->bucketLabel($row->usage_date),
                    'suggestions'    => 0,
                    'acceptances'    => 0,
                    'lines_accepted' => 0,
                    'chat'           => 0,
                ];
            }

            $buckets[$key]['suggestions']    += (int) $row->code_suggestions;
            $buckets[$key]['acceptances']    += (int) $row->code_acceptances;
            $buckets[$key]['lines_accepted'] += (int) $row->lines_accepted;
            $buckets[$key]['chat']           += (int) $row->chat_interactions;
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Breakdown by language, editor, or model parsed from raw JSON.
     * Returns ['label' => string, 'lines_accepted' => int, 'suggestions' => int, ...]
     */
    public function breakdown(?CopilotUser $user, string $dimension, Period $period): array
    {
        [$from, $to] = $period->dateRange();

        $query = DailyUsage::query()
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()]);

        if ($user !== null) {
            $query->where('copilot_user_id', $user->id);
        }

        $usages = $query->get(['raw']);

        // Parse the raw NDJSON records for breakdown dimension
        $totals = [];

        foreach ($usages as $usage) {
            $raw = $usage->raw;

            // The GitHub API returns breakdowns inside the raw record.
            // Keys tried: 'editors', 'languages', 'models', 'copilot_ide_code_completions'
            $items = $this->extractDimensionItems($raw, $dimension);

            foreach ($items as $key => $metrics) {
                if (! isset($totals[$key])) {
                    $totals[$key] = ['label' => $key, 'suggestions' => 0, 'acceptances' => 0, 'lines_suggested' => 0, 'lines_accepted' => 0];
                }
                $totals[$key]['suggestions']   += (int) ($metrics['suggestions'] ?? $metrics['total_code_suggestions'] ?? 0);
                $totals[$key]['acceptances']   += (int) ($metrics['acceptances'] ?? $metrics['total_code_acceptances'] ?? 0);
                $totals[$key]['lines_suggested'] += (int) ($metrics['lines_suggested'] ?? $metrics['total_lines_suggested'] ?? 0);
                $totals[$key]['lines_accepted'] += (int) ($metrics['lines_accepted'] ?? $metrics['total_lines_accepted'] ?? 0);
            }
        }

        usort($totals, fn ($a, $b) => $b['lines_accepted'] <=> $a['lines_accepted']);

        return array_values(array_slice($totals, 0, 10));
    }

    /**
     * Per-member totals sorted by lines_accepted (admin leaderboard).
     */
    public function leaderboard(Period $period): array
    {
        [$from, $to] = $period->dateRange();

        return DailyUsage::query()
            ->with('copilotUser')
            ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                'copilot_user_id,
                 SUM(code_suggestions)  as suggestions,
                 SUM(code_acceptances)  as acceptances,
                 SUM(lines_suggested)   as lines_suggested,
                 SUM(lines_accepted)    as lines_accepted,
                 SUM(chat_interactions) as chat,
                 COUNT(DISTINCT usage_date) as active_days'
            )
            ->groupBy('copilot_user_id')
            ->orderByDesc('lines_accepted')
            ->get()
            ->map(fn ($row) => [
                'user'           => $row->copilotUser,
                'suggestions'    => (int) $row->suggestions,
                'acceptances'    => (int) $row->acceptances,
                'lines_suggested' => (int) $row->lines_suggested,
                'lines_accepted' => (int) $row->lines_accepted,
                'chat'           => (int) $row->chat,
                'active_days'    => (int) $row->active_days,
                'acceptance_rate' => $row->suggestions > 0 ? round($row->acceptances / $row->suggestions * 100, 1) : 0.0,
            ])
            ->toArray();
    }

    private function extractDimensionItems(array $raw, string $dimension): array
    {
        // GitHub's NDJSON per-user records nest breakdowns differently depending on API version.
        // We try several known paths gracefully.
        $map = [
            'language' => ['languages', 'copilot_ide_code_completions.languages'],
            'editor'   => ['editors', 'copilot_ide_code_completions.editors'],
            'model'    => ['models', 'copilot_ide_code_completions.models'],
        ];

        $paths = $map[$dimension] ?? [$dimension . 's'];

        foreach ($paths as $path) {
            $value = data_get($raw, str_replace('.', '.', $path));
            if (is_array($value) && count($value) > 0) {
                // Array of objects with 'name' or 'language'/'editor'/'model' key
                $result = [];
                foreach ($value as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $key = $item['name'] ?? $item[$dimension] ?? $item['language'] ?? $item['editor'] ?? $item['model'] ?? 'unknown';
                    $result[$key] = $item;
                }
                return $result;
            }
        }

        return [];
    }
}
