<?php

namespace App\Console\Commands;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Services\Github\CopilotMetricsClient;
use App\Services\Github\UsageMetricsParser;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCopilotUsage extends Command
{
    protected $signature = 'copilot:sync-usage
                            {--day= : Sync a specific day (YYYY-MM-DD), defaults to yesterday}
                            {--backfill= : Sync the last N days}';

    protected $description = 'Fetch and store GitHub Copilot per-user usage data';

    public function handle(CopilotMetricsClient $client, UsageMetricsParser $parser): int
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
                    $this->upsertRow($parser, $day, $row);
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

    private function upsertRow(UsageMetricsParser $parser, Carbon $day, array $row): void
    {
        $identity = $parser->identity($row);

        if ($identity['github_id'] === '' && $identity['github_login'] === '') {
            return;
        }

        $copilotUser = CopilotUser::updateOrCreate(
            ['github_id' => $identity['github_id'] ?: $identity['github_login']],
            [
                'github_login' => $identity['github_login'] ?: $identity['github_id'],
                'name'         => $identity['name'],
                'avatar_url'   => $identity['avatar_url'],
            ]
        );

        $metrics = $parser->summarize($row);
        $extras  = $parser->extras($row);

        DailyUsage::updateOrCreate(
            [
                'copilot_user_id' => $copilotUser->id,
                'usage_date'      => $day->toDateString(),
            ],
            $metrics + $extras + ['raw' => $row]
        );
    }
}
