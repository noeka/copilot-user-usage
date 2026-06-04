<?php

namespace App\Services\Github;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

class CopilotMetricsClient
{
    private GithubAppAuthenticator $auth;
    private string $org;

    public function __construct(GithubAppAuthenticator $auth)
    {
        $this->auth = $auth;
        $this->org  = (string) config('copilot.org');
    }

    /**
     * Fetch per-user usage rows for a given day.
     * Returns an array of associative arrays (one per user).
     */
    public function usersReport(CarbonInterface $day): array
    {
        $token = $this->auth->token();

        $response = Http::withToken($token)
            ->accept('application/vnd.github+json')
            ->withHeader('X-GitHub-Api-Version', '2022-11-28')
            ->get("https://api.github.com/orgs/{$this->org}/copilot/metrics/reports/users-1-day", [
                'day' => $day->toDateString(),
            ]);

        if ($response->status() === 404) {
            // No data available for this day yet
            return [];
        }

        if (! $response->successful()) {
            throw new \RuntimeException(
                "GitHub Copilot metrics API error [{$response->status()}]: " . $response->body()
            );
        }

        $downloadLinks = $response->json('download_links') ?? [];

        $rows = [];
        foreach ($downloadLinks as $url) {
            $ndjson = Http::get($url);

            if (! $ndjson->successful()) {
                throw new \RuntimeException("Failed to download NDJSON report from signed URL.");
            }

            $rows = array_merge($rows, $this->parseNdjson($ndjson->body()));
        }

        return $rows;
    }

    /**
     * Get org membership/role for a user (uses user's OAuth token).
     * Returns 'admin' | 'member' | null (not a member).
     */
    public function orgMembership(string $userToken): ?string
    {
        $response = Http::withToken($userToken)
            ->accept('application/vnd.github+json')
            ->withHeader('X-GitHub-Api-Version', '2022-11-28')
            ->get("https://api.github.com/user/memberships/orgs/{$this->org}");

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $role  = $response->json('role');   // 'admin' | 'member'
        $state = $response->json('state');  // 'active' | 'pending'

        if ($state !== 'active') {
            return null;
        }

        return $role;
    }

    private function parseNdjson(string $body): array
    {
        $rows = [];
        foreach (explode("\n", trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }
}
