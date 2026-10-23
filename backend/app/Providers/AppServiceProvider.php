<?php

namespace App\Providers;

use App\Services\Analyst\AnalystClient;
use App\Services\Analyst\GrokAnalystClient;
use App\Services\Analyst\StubAnalystClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnalystClient::class, fn () => match (config('services.analyst.driver')) {
            'grok' => new GrokAnalystClient(
                config('services.grok.url'),
                config('services.grok.key'),
                config('services.grok.model'),
                config('services.analyst.timeout'),
            ),
            default => new StubAnalystClient,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Requests reach Laravel through the Nuxt proxy, so build links on the public URL, not the container host.
        URL::forceRootUrl(config('app.url'));
    }
}
