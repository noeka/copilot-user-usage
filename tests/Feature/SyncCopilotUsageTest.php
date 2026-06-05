<?php

namespace Tests\Feature;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Services\Github\CopilotMetricsClient;
use App\Services\Github\GithubAppAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCopilotUsageTest extends TestCase
{
    use RefreshDatabase;

    private function fakeClient(array $rows): void
    {
        $this->instance(GithubAppAuthenticator::class, tap(
            $this->createMock(GithubAppAuthenticator::class),
            fn ($m) => $m->method('token')->willReturn('fake-token')
        ));

        $this->mock(CopilotMetricsClient::class, function ($mock) use ($rows) {
            $mock->shouldReceive('usersReport')->andReturn($rows);
        });
    }

    /**
     * A flat users-1-day record:
     * suggestions=80, acceptances=30, lines_suggested=100, lines_accepted=42, interactions=5
     */
    private function nestedRow(): array
    {
        return [
            'day'        => '2025-11-01',
            'user_login' => 'alice',
            'user_id'    => '1',

            'user_initiated_interaction_count' => 5,
            'code_generation_activity_count'   => 80,
            'code_acceptance_activity_count'   => 30,
            'loc_suggested_to_add_sum'         => 100,
            'loc_suggested_to_delete_sum'      => 0,
            'loc_added_sum'                    => 42,
            'loc_deleted_sum'                  => 10,

            'used_agent'               => true,
            'used_chat'                => true,
            'used_cli'                 => false,
            'used_copilot_coding_agent' => false,
            'used_copilot_cloud_agent'  => false,

            'ai_adoption_phase' => ['phase_number' => 1, 'phase' => 'Phase 1', 'version' => 'v1'],

            'totals_by_ide' => [[
                'ide'                              => 'vscode',
                'code_generation_activity_count'   => 80,
                'code_acceptance_activity_count'   => 30,
                'loc_suggested_to_add_sum'         => 100,
                'loc_suggested_to_delete_sum'      => 0,
                'loc_added_sum'                    => 42,
                'loc_deleted_sum'                  => 10,
            ]],

            'totals_by_feature' => [[
                'feature'                          => 'agent_edit',
                'user_initiated_interaction_count' => 0,
                'code_generation_activity_count'   => 80,
                'code_acceptance_activity_count'   => 30,
                'loc_suggested_to_add_sum'         => 100,
                'loc_suggested_to_delete_sum'      => 0,
                'loc_added_sum'                    => 42,
                'loc_deleted_sum'                  => 10,
            ]],

            'totals_by_language_feature' => [[
                'language'                         => 'python',
                'feature'                          => 'agent_edit',
                'code_generation_activity_count'   => 80,
                'code_acceptance_activity_count'   => 30,
                'loc_suggested_to_add_sum'         => 100,
                'loc_suggested_to_delete_sum'      => 0,
                'loc_added_sum'                    => 42,
                'loc_deleted_sum'                  => 10,
            ]],

            'totals_by_language_model'  => [],
            'totals_by_model_feature'   => [],
        ];
    }

    public function test_sync_creates_users_and_usages(): void
    {
        $this->fakeClient([$this->nestedRow()]);

        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])
            ->assertSuccessful();

        $this->assertDatabaseHas('copilot_users', ['github_login' => 'alice']);
        $this->assertDatabaseHas('daily_usages', [
            'lines_accepted'             => 42,
            'code_suggestions'           => 80,
            'chat_interactions'          => 5,
            'user_initiated_interactions' => 5,
            'used_agent'                 => true,
            'adoption_phase'             => 'Phase 1',
        ]);
        $this->assertEquals(1, DailyUsage::whereDate('usage_date', '2025-11-01')->count());
    }

    public function test_sync_is_idempotent(): void
    {
        $rows = [$this->nestedRow()];

        $this->fakeClient($rows);
        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])->assertSuccessful();

        $this->fakeClient($rows);
        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])->assertSuccessful();

        $this->assertEquals(1, DailyUsage::count(), 'Idempotent sync must not create duplicates');
        $this->assertEquals(1, CopilotUser::count());
    }
}
