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

    public function test_sync_creates_users_and_usages(): void
    {
        $this->fakeClient([
            ['user_id' => '1', 'login' => 'alice', 'total_lines_suggested' => 100, 'total_lines_accepted' => 42, 'total_code_suggestions' => 80, 'total_code_acceptances' => 30, 'total_chat_interactions' => 5],
        ]);

        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])
            ->assertSuccessful();

        $this->assertDatabaseHas('copilot_users', ['github_login' => 'alice']);
        $this->assertDatabaseHas('daily_usages', ['lines_accepted' => 42]);
        $this->assertEquals(1, DailyUsage::whereDate('usage_date', '2025-11-01')->count());
    }

    public function test_sync_is_idempotent(): void
    {
        $rows = [
            ['user_id' => '1', 'login' => 'alice', 'total_lines_suggested' => 100, 'total_lines_accepted' => 42, 'total_code_suggestions' => 80, 'total_code_acceptances' => 30, 'total_chat_interactions' => 5],
        ];

        $this->fakeClient($rows);
        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])->assertSuccessful();

        $this->fakeClient($rows);
        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])->assertSuccessful();

        $this->assertEquals(1, DailyUsage::count(), 'Idempotent sync must not create duplicates');
        $this->assertEquals(1, CopilotUser::count());
    }
}
