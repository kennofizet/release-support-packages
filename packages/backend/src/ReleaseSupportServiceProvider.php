<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport;

use Kennofizet\ReleaseSupport\Services\ReleaseSupportService;
use Illuminate\Support\ServiceProvider;

class ReleaseSupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/release-support.php', 'release-support');
        $this->app->singleton(ReleaseSupportService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/Config/release-support.php' => config_path('release-support.php'),
        ], 'release-support-config');

        $this->publishes([
            __DIR__ . '/Migrations' => database_path('migrations'),
        ], 'release-support-migrations');

        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}
