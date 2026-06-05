<?php

namespace Tests\Feature;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Services\Github\UsageMetricsParser;
use App\Services\Period;
use App\Services\UsageReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Drives the full pipeline (parser → DB → report service → views) with an
 * anonymised copy of a real GitHub "users-1-day" report, so the dashboard is
 * validated against the actual response shape rather than synthetic data.
 */
class RealReportShapeTest extends TestCase
{
    use RefreshDatabase;

    private UsageReportService $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new UsageReportService;
        Carbon::setTestNow('2026-06-05');
        $this->ingestFixture();
    }

    /** Mimics SyncCopilotUsage::upsertRow for every row in the fixture. */
    private function ingestFixture(): void
    {
        $parser = new UsageMetricsParser;
        $rows = json_decode((string) file_get_contents(base_path('tests/Fixtures/users_report_sample.json')), true);

        foreach ($rows as $row) {
            $identity = $parser->identity($row);

            $user = CopilotUser::updateOrCreate(
                ['github_id' => $identity['github_id'] ?: $identity['github_login']],
                ['github_login' => $identity['github_login'], 'name' => $identity['name'], 'avatar_url' => $identity['avatar_url']],
            );

            DailyUsage::updateOrCreate(
                ['copilot_user_id' => $user->id, 'usage_date' => '2026-06-04'],
                $parser->summarize($row) + $parser->extras($row) + ['raw' => $row],
            );
        }
    }

    public function test_token_totals_match_the_report(): void
    {
        $summary = $this->report->summary(null, Period::Month);

        // prompt: 8_424_700 + 2_175_353 ; output: 79_480 + 23_268
        $this->assertSame(10_600_053, $summary['cli_prompt_tokens']);
        $this->assertSame(102_748, $summary['cli_output_tokens']);
        $this->assertSame(10_702_801, $summary['cli_total_tokens']);
        $this->assertSame(202, $summary['cli_requests']);
    }

    public function test_model_breakdown_ranks_by_interactions_and_includes_cli_models(): void
    {
        $byModel = $this->report->breakdown(null, 'model', Period::Month);
        $byLabel = collect($byModel)->keyBy('label');

        // gpt-5.3-codex: 43 (dev-one) + 32 (dev-mixed) = 75 interactions, the top model.
        $this->assertSame('gpt-5.3-codex', $byModel[0]['label']);
        $this->assertSame(75, $byModel[0]['interactions']);

        // The CLI-only user's model surfaces — totals_by_language_model would
        // have hidden this under an empty "others" bucket.
        $this->assertTrue($byLabel->has('gpt-5.4'));
        $this->assertSame(8, $byLabel['gpt-5.4']['interactions']);
    }

    public function test_cli_only_user_model_usage_is_visible(): void
    {
        $user = CopilotUser::where('github_login', 'dev-cli-only')->firstOrFail();

        $byModel = $this->report->breakdown($user, 'model', Period::Month);
        $labels = array_column($byModel, 'label');

        $this->assertContains('gpt-5.4', $labels);
        $this->assertNotContains('others', array_slice($labels, 0, 1)); // not ranked top by a zero bucket
    }

    public function test_dashboard_partials_render_with_real_shape(): void
    {
        $user = CopilotUser::where('github_login', 'dev-mixed')->firstOrFail();

        $shared = [
            'chartTheme' => View::shared('chartTheme'),
            'period' => Period::Month,
            'summary' => $this->report->summary($user, Period::Month),
            'timeSeries' => $this->report->timeSeries($user, Period::Month),
        ];

        $tokens = View::make('partials.token-usage', $shared)->render();
        $this->assertStringContainsString('<svg', $tokens);

        $breakdown = View::make('partials.breakdown-charts', $shared + [
            'byLanguage' => $this->report->breakdown($user, 'language', Period::Month),
            'byEditor' => $this->report->breakdown($user, 'editor', Period::Month),
            'byFeature' => $this->report->breakdown($user, 'feature', Period::Month),
            'byModel' => $this->report->breakdown($user, 'model', Period::Month),
        ])->render();

        $this->assertStringContainsString('<svg', $breakdown);
        $this->assertStringContainsString('gpt-5.3-codex', $breakdown);
    }
}
