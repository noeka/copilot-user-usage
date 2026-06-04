<?php

namespace App\Providers;

use App\Services\Github\CopilotMetricsClient;
use App\Services\Github\GithubAppAuthenticator;
use App\Services\UsageReportService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GithubAppAuthenticator::class);
        $this->app->singleton(CopilotMetricsClient::class);
        $this->app->singleton(UsageReportService::class);
    }

    public function boot(): void
    {
        //
    }
}
