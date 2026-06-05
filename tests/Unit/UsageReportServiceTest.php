<?php

namespace Tests\Unit;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Services\Period;
use App\Services\UsageReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsageReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsageReportService;
    }

    private function seedUser(string $login = 'alice'): CopilotUser
    {
        return CopilotUser::create([
            'github_id' => $login,
            'github_login' => $login,
        ]);
    }

    private function seedUsage(CopilotUser $user, string $date, array $overrides = []): DailyUsage
    {
        return DailyUsage::create(array_merge([
            'copilot_user_id' => $user->id,
            'usage_date' => $date,
            'code_suggestions' => 100,
            'code_acceptances' => 30,
            'lines_suggested' => 200,
            'lines_accepted' => 80,
            'chat_interactions' => 5,
            'engaged' => true,
            'raw' => [],
        ], $overrides));
    }

    public function test_summary_totals_within_period(): void
    {
        Carbon::setTestNow('2025-11-15');

        $user = $this->seedUser();
        $this->seedUsage($user, '2025-11-01', ['lines_accepted' => 100]);
        $this->seedUsage($user, '2025-11-10', ['lines_accepted' => 50]);
        $this->seedUsage($user, '2025-10-31', ['lines_accepted' => 999]); // outside month

        $summary = $this->service->summary($user, Period::Month);

        $this->assertEquals(150, $summary['lines_accepted']);
        $this->assertEquals(2, $summary['active_days']);
    }

    public function test_time_series_groups_by_month(): void
    {
        Carbon::setTestNow('2025-12-01');

        $user = $this->seedUser();
        // Both dates must be >= Period::All start (2025-10-10)
        $this->seedUsage($user, '2025-10-15', ['lines_accepted' => 40]);
        $this->seedUsage($user, '2025-11-01', ['lines_accepted' => 60]);

        $series = $this->service->timeSeries($user, Period::All);

        $this->assertCount(2, $series);
        $this->assertEquals(40, $series[0]['lines_accepted']);
        $this->assertEquals(60, $series[1]['lines_accepted']);
    }

    public function test_breakdown_by_language_reads_raw(): void
    {
        Carbon::setTestNow('2025-11-15');

        $user = $this->seedUser();
        $this->seedUsage($user, '2025-11-01', ['raw' => [
            'totals_by_language_feature' => [
                [
                    'language' => 'python',
                    'feature' => 'chat_panel_custom_mode',
                    'code_generation_activity_count' => 60,
                    'code_acceptance_activity_count' => 20,
                    'loc_suggested_to_add_sum' => 120,
                    'loc_added_sum' => 90,
                ],
                [
                    'language' => 'php',
                    'feature' => 'agent_edit',
                    'code_generation_activity_count' => 30,
                    'code_acceptance_activity_count' => 10,
                    'loc_suggested_to_add_sum' => 60,
                    'loc_added_sum' => 40,
                ],
            ],
        ]]);

        $breakdown = $this->service->breakdown($user, 'language', Period::Month);

        $this->assertCount(2, $breakdown);
        // Ordered by lines_accepted desc: python (90) before php (40).
        $this->assertEquals('python', $breakdown[0]['label']);
        $this->assertEquals(90, $breakdown[0]['lines_accepted']);
        $this->assertEquals(60, $breakdown[0]['suggestions']);
        $this->assertEquals('php', $breakdown[1]['label']);
    }

    public function test_time_series_accumulates_tokens(): void
    {
        Carbon::setTestNow('2025-11-15');

        $user = $this->seedUser();
        $this->seedUsage($user, '2025-11-01', ['cli_prompt_tokens' => 1000, 'cli_output_tokens' => 400]);
        $this->seedUsage($user, '2025-11-02', ['cli_prompt_tokens' => 500, 'cli_output_tokens' => 250]);

        $series = $this->service->timeSeries($user, Period::Month);

        $this->assertEquals(1000, $series[0]['prompt_tokens']);
        $this->assertEquals(400, $series[0]['output_tokens']);
        $this->assertEquals(1400, $series[0]['tokens']);
        $this->assertEquals(750, $series[1]['tokens']);
    }

    public function test_summary_includes_total_tokens(): void
    {
        Carbon::setTestNow('2025-11-15');

        $user = $this->seedUser();
        $this->seedUsage($user, '2025-11-01', ['cli_prompt_tokens' => 1000, 'cli_output_tokens' => 400]);

        $summary = $this->service->summary($user, Period::Month);

        $this->assertEquals(1000, $summary['cli_prompt_tokens']);
        $this->assertEquals(400, $summary['cli_output_tokens']);
        $this->assertEquals(1400, $summary['cli_total_tokens']);
    }

    public function test_breakdown_by_model_reads_raw(): void
    {
        Carbon::setTestNow('2025-11-15');

        $user = $this->seedUser();
        $this->seedUsage($user, '2025-11-01', ['raw' => [
            'totals_by_model_feature' => [
                ['model' => 'gpt-4o', 'feature' => 'chat_panel_agent_mode', 'user_initiated_interaction_count' => 10, 'loc_added_sum' => 120],
                ['model' => 'gpt-4o', 'feature' => 'copilot_cli', 'user_initiated_interaction_count' => 5, 'loc_added_sum' => 0],
                ['model' => 'claude-sonnet', 'feature' => 'chat_panel_agent_mode', 'user_initiated_interaction_count' => 8, 'loc_added_sum' => 50],
            ],
        ]]);

        $breakdown = $this->service->breakdown($user, 'model', Period::Month);

        $this->assertCount(2, $breakdown);
        // Ranked by interactions: gpt-4o (10 + 5 = 15) before claude-sonnet (8).
        $this->assertEquals('gpt-4o', $breakdown[0]['label']);
        $this->assertEquals(15, $breakdown[0]['interactions']);
        $this->assertEquals(120, $breakdown[0]['lines_accepted']);
    }

    public function test_latest_day_period_resolves_to_most_recent_data_day(): void
    {
        Carbon::setTestNow('2025-11-20');

        $user = $this->seedUser();
        // Most recent data is the 18th, two days before "today".
        $this->seedUsage($user, '2025-11-18', ['lines_accepted' => 42]);
        $this->seedUsage($user, '2025-11-10', ['lines_accepted' => 99]);

        $summary = $this->service->summary($user, Period::Day);

        $this->assertEquals(1, $summary['active_days']);
        $this->assertEquals(42, $summary['lines_accepted']);
    }

    public function test_leaderboard_orders_by_lines_accepted(): void
    {
        Carbon::setTestNow('2025-11-15');

        $alice = $this->seedUser('alice');
        $bob = $this->seedUser('bob');

        $this->seedUsage($alice, '2025-11-01', ['lines_accepted' => 10]);
        $this->seedUsage($bob, '2025-11-01', ['lines_accepted' => 99]);

        $board = $this->service->leaderboard(Period::Month);

        $this->assertEquals('bob', $board[0]['user']->github_login);
        $this->assertEquals('alice', $board[1]['user']->github_login);
    }
}
