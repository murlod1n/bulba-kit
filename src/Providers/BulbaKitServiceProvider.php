<?php

namespace Nktlksvch\BulbaKit\Providers;

use Illuminate\Support\ServiceProvider;
use Nktlksvch\BulbaKit\Console\Commands\BulbaInstallCommand;
use Nktlksvch\BulbaKit\Console\Commands\BulbaMakeResource;
use Nktlksvch\BulbaKit\Modules\ModuleRegistry;
use Nktlksvch\BulbaKit\Modules\Redirects\RedirectsModule;
use Nktlksvch\BulbaKit\Modules\WebsiteSettings\WebsiteSettingsModule;

class BulbaKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/bulba.php', 'bulba');

        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry;
            $registry->register(new WebsiteSettingsModule);
            $registry->register(new RedirectsModule);

            return $registry;
        });
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
