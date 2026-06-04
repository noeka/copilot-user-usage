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

    /** A nested users-1-day record summing to 80 suggestions / 30 acceptances / 100 lines suggested / 42 lines accepted / 5 chats. */
    private function nestedRow(): array
    {
        return [
            'date'       => '2025-11-01',
            'user_login' => 'alice',
            'user_id'    => '1',
            'copilot_ide_code_completions' => [
                'editors' => [[
                    'name'   => 'vscode',
                    'models' => [[
                        'name'      => 'default',
                        'languages' => [[
                            'name' => 'python',
                            'total_code_suggestions'     => 80,
                            'total_code_acceptances'     => 30,
                            'total_code_lines_suggested' => 100,
                            'total_code_lines_accepted'  => 42,
                        ]],
                    ]],
                ]],
            ],
            'copilot_ide_chat' => [
                'editors' => [[
                    'name'   => 'vscode',
                    'models' => [['name' => 'default', 'total_chats' => 5]],
                ]],
            ],
        ];
    }

    public function test_sync_creates_users_and_usages(): void
    {
        $this->fakeClient([$this->nestedRow()]);

        $this->artisan('copilot:sync-usage', ['--day' => '2025-11-01'])
            ->assertSuccessful();

        $this->assertDatabaseHas('copilot_users', ['github_login' => 'alice']);
        $this->assertDatabaseHas('daily_usages', ['lines_accepted' => 42, 'code_suggestions' => 80, 'chat_interactions' => 5]);
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
