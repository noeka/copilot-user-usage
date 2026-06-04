<?php

namespace App\Services\Github;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GithubAppAuthenticator
{
    private string $appId;
    private string $privateKey;
    private string $installationId;

    public function __construct()
    {
        $this->appId          = (string) config('copilot.github_app_id');
        $this->installationId = (string) config('copilot.github_app_installation_id');

        $path = config('copilot.github_app_private_key_path');
        $pem  = config('copilot.github_app_private_key');

        if ($path && file_exists($path)) {
            $this->privateKey = file_get_contents($path);
        } elseif ($pem) {
            $this->privateKey = str_replace('\n', "\n", $pem);
        } else {
            $this->privateKey = '';
        }
    }

    /** Returns a valid installation access token (cached until near expiry). */
    public function token(): string
    {
        $cacheKey = "github_app_token_{$this->installationId}";

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $jwt = $this->buildJwt();

            $response = Http::withToken($jwt, 'Bearer')
                ->accept('application/vnd.github+json')
                ->withHeader('X-GitHub-Api-Version', '2022-11-28')
                ->post("https://api.github.com/app/installations/{$this->installationId}/access_tokens");

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "Failed to get GitHub App installation token: " . $response->body()
                );
            }

            return $response->json('token');
        });
    }

    private function buildJwt(): string
    {
        $now = time();

        $header  = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iat' => $now - 60,
            'exp' => $now + 600,
            'iss' => $this->appId,
        ]));

        $data = "{$header}.{$payload}";

        $key = openssl_pkey_get_private($this->privateKey);
        if ($key === false) {
            throw new \RuntimeException('Invalid GitHub App private key.');
        }

        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);

        return "{$data}." . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
