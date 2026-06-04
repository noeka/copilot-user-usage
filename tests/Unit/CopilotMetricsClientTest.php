<?php

namespace Tests\Unit;

use App\Services\Github\CopilotMetricsClient;
use App\Services\Github\GithubAppAuthenticator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CopilotMetricsClientTest extends TestCase
{
    private function makeClient(): CopilotMetricsClient
    {
        $auth = $this->createMock(GithubAppAuthenticator::class);
        $auth->method('token')->willReturn('fake-token');

        return new CopilotMetricsClient($auth);
    }

    public function test_parses_ndjson_report(): void
    {
        config(['copilot.org' => 'test-org']);

        $ndjson = implode("\n", [
            json_encode(['user_id' => '1', 'user_login' => 'alice', 'copilot_ide_code_completions' => ['editors' => []]]),
            json_encode(['user_id' => '2', 'user_login' => 'bob',   'copilot_ide_code_completions' => ['editors' => []]]),
        ]);

        Http::fake([
            'api.github.com/orgs/test-org/copilot/metrics/reports/users-1-day*' => Http::response([
                'download_links' => ['https://example.com/report.ndjson'],
            ]),
            'example.com/report.ndjson' => Http::response($ndjson, 200, ['Content-Type' => 'application/x-ndjson']),
        ]);

        $rows = $this->makeClient()->usersReport(now());

        $this->assertCount(2, $rows);
        $this->assertEquals('alice', $rows[0]['user_login']);
        $this->assertEquals('bob', $rows[1]['user_login']);
    }

    public function test_returns_empty_on_404(): void
    {
        config(['copilot.org' => 'test-org']);

        Http::fake([
            'api.github.com/orgs/test-org/copilot/metrics/reports/users-1-day*' => Http::response([], 404),
        ]);

        $rows = $this->makeClient()->usersReport(now());

        $this->assertEmpty($rows);
    }
}
