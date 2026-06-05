<?php

namespace App\Services;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Services\Github\UsageMetricsParser;

class UsageReportService
{
    private UsageMetricsParser $parser;

    public function __construct(?UsageMetricsParser $parser = null)
    {
        $this->parser = $parser ?? new UsageMetricsParser;
    }

    /**
     * Aggregate summary totals for a period.
     * Pass $user = null for org-wide totals (admin only).
     */
    public function summary(?CopilotUser $user, Period $period): array
    {
        [$from, $to] = $period->dateRange();

        $query = DailyUsage::query()
            ->whereBetween('usage_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($user !== null) {
            $query->where('copilot_user_id', $user->id);
        }

        $row = $query->selectRaw(
            'SUM(code_suggestions) as suggestions,
             SUM(code_acceptances) as acceptances,
             SUM(lines_suggested)  as lines_suggested,
             SUM(lines_accepted)   as lines_accepted,
             SUM(chat_interactions) as chat,
             SUM(user_initiated_interactions) as interactions,
             SUM(lines_deleted) as lines_deleted,
             SUM(cli_request_count) as cli_requests,
             SUM(cli_output_tokens) as cli_output_tokens,
             SUM(cli_prompt_tokens) as cli_prompt_tokens,
             COUNT(DISTINCT usage_date) as active_days,
             COUNT(DISTINCT copilot_user_id) as active_users'
        )->first();

        $suggestions = (int) ($row->suggestions ?? 0);
        $acceptances = (int) ($row->acceptances ?? 0);

        $adoptionPhase = null;
        if ($user !== null) {
            $adoptionPhase = DailyUsage::query()
                ->where('copilot_user_id', $user->id)
                ->whereBetween('usage_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->whereNotNull('adoption_phase')
                ->latest('usage_date')
                ->value('adoption_phase');
        }

        return [
            'code_suggestions' => $suggestions,
            'code_acceptances' => $acceptances,
            'acceptance_rate' => $suggestions > 0 ? round($acceptances / $suggestions * 100, 1) : 0.0,
            'lines_suggested' => (int) ($row->lines_suggested ?? 0),
            'lines_accepted' => (int) ($row->lines_accepted ?? 0),
            'chat_interactions' => (int) ($row->chat ?? 0),
            'user_initiated_interactions' => (int) ($row->interactions ?? 0),
            'lines_deleted' => (int) ($row->lines_deleted ?? 0),
            'cli_requests' => (int) ($row->cli_requests ?? 0),
            'cli_output_tokens' => (int) ($row->cli_output_tokens ?? 0),
            'cli_prompt_tokens' => (int) ($row->cli_prompt_tokens ?? 0),
            'cli_total_tokens' => (int) ($row->cli_output_tokens ?? 0) + (int) ($row->cli_prompt_tokens ?? 0),
            'active_days' => (int) ($row->active_days ?? 0),
            'active_users' => (int) ($row->active_users ?? 0),
            'adoption_phase' => $adoptionPhase,
        ];
    }

    /**
     * Time-series data for a line chart (suggestions, acceptances, chat per bucket).
     */
    public function timeSeries(?CopilotUser $user, Period $period): array
    {
        [$from, $to] = $period->dateRange();

        $query = DailyUsage::query()
            ->whereBetween('usage_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($user !== null) {
            $query->where('copilot_user_id', $user->id);
        }

        $rows = $query
            ->orderBy('usage_date')
            ->get(['usage_date', 'code_suggestions', 'code_acceptances', 'lines_accepted', 'chat_interactions', 'cli_prompt_tokens', 'cli_output_tokens']);

        // Bucket in PHP so this works on any database (MySQL/Postgres/SQLite).
        $buckets = [];
        foreach ($rows as $row) {
            $key = $period->bucketKey($row->usage_date);

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'label' => $period->bucketLabel($row->usage_date),
                    'suggestions' => 0,
                    'acceptances' => 0,
                    'lines_accepted' => 0,
                    'chat' => 0,
                    'prompt_tokens' => 0,
                    'output_tokens' => 0,
                    'tokens' => 0,
                ];
            }

            $buckets[$key]['suggestions'] += (int) $row->code_suggestions;
            $buckets[$key]['acceptances'] += (int) $row->code_acceptances;
            $buckets[$key]['lines_accepted'] += (int) $row->lines_accepted;
            $buckets[$key]['chat'] += (int) $row->chat_interactions;
            $buckets[$key]['prompt_tokens'] += (int) $row->cli_prompt_tokens;
            $buckets[$key]['output_tokens'] += (int) $row->cli_output_tokens;
            $buckets[$key]['tokens'] += (int) $row->cli_prompt_tokens + (int) $row->cli_output_tokens;
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
            ->whereBetween('usage_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        if ($user !== null) {
            $query->where('copilot_user_id', $user->id);
        }

        $usages = $query->get(['raw']);

        // Accumulate the nested code metrics from each raw record by dimension.
        $totals = [];

        foreach ($usages as $usage) {
            foreach ($this->parser->breakdownItems($usage->raw ?? [], $dimension) as $key => $metrics) {
                if (! isset($totals[$key])) {
                    $totals[$key] = ['label' => $key, 'suggestions' => 0, 'acceptances' => 0, 'lines_suggested' => 0, 'lines_accepted' => 0, 'interactions' => 0];
                }
                $totals[$key]['suggestions'] += $metrics['total_code_suggestions'];
                $totals[$key]['acceptances'] += $metrics['total_code_acceptances'];
                $totals[$key]['lines_suggested'] += $metrics['total_code_lines_suggested'];
                $totals[$key]['lines_accepted'] += $metrics['total_code_lines_accepted'];
                $totals[$key]['interactions'] += $metrics['total_interactions'];
            }
        }

        // Models are ranked by interactions: acceptances/accepted lines are near
        // zero for chat/agent/CLI usage, so interactions is the meaningful
        // "how much is this model used" signal. Other dimensions rank by lines.
        $sortKey = $dimension === 'model' ? 'interactions' : 'lines_accepted';
        usort($totals, fn ($a, $b) => $b[$sortKey] <=> $a[$sortKey]);

        return array_values(array_slice($totals, 0, 10));
    }

    /**
     * Per-member totals sorted by engagement (user-initiated interactions).
     */
    public function leaderboard(Period $period): array
    {
        [$from, $to] = $period->dateRange();

        return DailyUsage::query()
            ->with('copilotUser')
            ->whereBetween('usage_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
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
            ->orderByRaw('SUM(chat_interactions) DESC')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->copilotUser,
                'suggestions' => (int) $row->suggestions,
                'acceptances' => (int) $row->acceptances,
                'lines_suggested' => (int) $row->lines_suggested,
                'lines_accepted' => (int) $row->lines_accepted,
                'chat' => (int) $row->chat,
                'active_days' => (int) $row->active_days,
                'acceptance_rate' => $row->suggestions > 0 ? round($row->acceptances / $row->suggestions * 100, 1) : 0.0,
            ])
            ->toArray();
    }
}
