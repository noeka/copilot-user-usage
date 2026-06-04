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
        $this->service = new UsageReportService();
    }

    private function seedUser(string $login = 'alice'): CopilotUser
    {
        return CopilotUser::create([
            'github_id'    => $login,
            'github_login' => $login,
        ]);
    }

    private function seedUsage(CopilotUser $user, string $date, array $overrides = []): DailyUsage
    {
        return DailyUsage::create(array_merge([
            'copilot_user_id'   => $user->id,
            'usage_date'        => $date,
            'code_suggestions'  => 100,
            'code_acceptances'  => 30,
            'lines_suggested'   => 200,
            'lines_accepted'    => 80,
            'chat_interactions' => 5,
            'engaged'           => true,
            'raw'               => [],
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

    public function test_leaderboard_orders_by_lines_accepted(): void
    {
        Carbon::setTestNow('2025-11-15');

        $alice = $this->seedUser('alice');
        $bob   = $this->seedUser('bob');

        $this->seedUsage($alice, '2025-11-01', ['lines_accepted' => 10]);
        $this->seedUsage($bob,   '2025-11-01', ['lines_accepted' => 99]);

        $board = $this->service->leaderboard(Period::Month);

        $this->assertEquals('bob', $board[0]['user']->github_login);
        $this->assertEquals('alice', $board[1]['user']->github_login);
    }
}
