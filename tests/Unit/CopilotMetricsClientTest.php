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
            json_encode(['user_id' => '1', 'login' => 'alice', 'total_lines_accepted' => 42, 'total_code_suggestions' => 100, 'total_code_acceptances' => 30]),
            json_encode(['user_id' => '2', 'login' => 'bob',   'total_lines_accepted' => 10, 'total_code_suggestions' => 50,  'total_code_acceptances' => 5]),
        ]);

        Http::fake([
            'api.github.com/orgs/test-org/copilot/metrics/reports/users-1-day*' => Http::response([
                'download_links' => ['https://example.com/report.ndjson'],
            ]),
            'example.com/report.ndjson' => Http::response($ndjson, 200, ['Content-Type' => 'application/x-ndjson']),
        ]);

        $rows = $this->makeClient()->usersReport(now());

        $this->assertCount(2, $rows);
        $this->assertEquals('alice', $rows[0]['login']);
        $this->assertEquals('bob', $rows[1]['login']);
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
