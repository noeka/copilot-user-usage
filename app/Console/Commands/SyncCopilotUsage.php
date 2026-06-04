<?php

namespace App\Console\Commands;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Services\Github\CopilotMetricsClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCopilotUsage extends Command
{
    protected $signature = 'copilot:sync-usage
                            {--day= : Sync a specific day (YYYY-MM-DD), defaults to yesterday}
                            {--backfill= : Sync the last N days}';

    protected $description = 'Fetch and store GitHub Copilot per-user usage data';

    public function handle(CopilotMetricsClient $client): int
    {
        $days = $this->daysToSync();

        $this->info(sprintf('Syncing %d day(s)...', count($days)));

        $synced = 0;
        $skipped = 0;

        foreach ($days as $day) {
            $this->line("  → {$day->toDateString()}");

            try {
                $rows = $client->usersReport($day);

                if (empty($rows)) {
                    $this->line("    no data");
                    $skipped++;
                    continue;
                }

                foreach ($rows as $row) {
                    $this->upsertRow($day, $row);
                }

                $synced++;
                $this->line("    {$day->toDateString()} — " . count($rows) . " user(s) stored");
            } catch (\Throwable $e) {
                $this->error("    Failed: " . $e->getMessage());
            }
        }

        $this->info("Done. Synced: {$synced}, Skipped (no data): {$skipped}");

        return self::SUCCESS;
    }

    /** @return Carbon[] */
    private function daysToSync(): array
    {
        if ($backfill = $this->option('backfill')) {
            $days = [];
            $n = max(1, (int) $backfill);
            for ($i = $n; $i >= 1; $i--) {
                $days[] = Carbon::today()->subDays($i);
            }
            return $days;
        }

        if ($day = $this->option('day')) {
            return [Carbon::parse($day)];
        }

        return [Carbon::yesterday()];
    }

    private function upsertRow(Carbon $day, array $row): void
    {
        // Field names as documented (adapt if GitHub changes them)
        $githubId    = (string) ($row['user_id'] ?? $row['id'] ?? '');
        $githubLogin = (string) ($row['login'] ?? $row['username'] ?? '');

        if ($githubId === '' && $githubLogin === '') {
            return;
        }

        $copilotUser = CopilotUser::updateOrCreate(
            ['github_id' => $githubId ?: $githubLogin],
            [
                'github_login' => $githubLogin ?: $githubId,
                'name'         => $row['name'] ?? null,
                'avatar_url'   => $row['avatar_url'] ?? null,
            ]
        );

        // Extract summary fields; try multiple known field name patterns
        $suggestions  = (int) ($row['total_code_suggestions'] ?? $row['code_suggestions'] ?? $row['suggestions'] ?? 0);
        $acceptances  = (int) ($row['total_code_acceptances'] ?? $row['code_acceptances'] ?? $row['acceptances'] ?? 0);
        $linesSugg    = (int) ($row['total_lines_suggested'] ?? $row['lines_suggested'] ?? 0);
        $linesAcc     = (int) ($row['total_lines_accepted'] ?? $row['lines_accepted'] ?? 0);
        $chat         = (int) ($row['total_chat_interactions'] ?? $row['chat_interactions'] ?? $row['chat_turns'] ?? 0);
        $engaged      = (bool) ($row['is_engaged'] ?? $row['engaged'] ?? ($suggestions > 0 || $chat > 0));

        DailyUsage::updateOrCreate(
            [
                'copilot_user_id' => $copilotUser->id,
                'usage_date'      => $day->toDateString(),
            ],
            [
                'code_suggestions'  => $suggestions,
                'code_acceptances'  => $acceptances,
                'lines_suggested'   => $linesSugg,
                'lines_accepted'    => $linesAcc,
                'chat_interactions' => $chat,
                'engaged'           => $engaged,
                'raw'               => $row,
            ]
        );
    }
}
