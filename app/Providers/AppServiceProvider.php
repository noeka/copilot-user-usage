<?php

namespace App\Providers;

use App\Services\Github\CopilotMetricsClient;
use App\Services\Github\GithubAppAuthenticator;
use App\Services\UsageReportService;
use App\Support\ChartTheme;
use Illuminate\Support\Facades\View;
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
        // Make the GitHub-themed chart palette available to every view so all
        // SVG charts render with the same dark, Primer-flavoured colors.
        View::share('chartTheme', ChartTheme::github());
    }
}
