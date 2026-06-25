<?php

namespace Nktlksvch\BulbaKit\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Nktlksvch\BulbaKit\Console\Commands\BulbaInstallCommand;
use Nktlksvch\BulbaKit\Console\Commands\BulbaMakeResource;

class BulbaKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/bulba.php', 'bulba');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BulbaMakeResource::class,
                BulbaInstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/bulba.php' => config_path('bulba.php'),
            ], 'bulba-config');

            $this->publishes([
                __DIR__.'/../Resources/stubs' => base_path('stubs/bulba'),
            ], 'bulba-stubs');
        }
    }
}
