<?php

namespace Nktlksvch\BulbaKit\Providers;

use Illuminate\Support\ServiceProvider;
use Nktlksvch\BulbaKit\Console\Commands\BulbaInstallCommand;
use Nktlksvch\BulbaKit\Console\Commands\BulbaMakeResource;
use Nktlksvch\BulbaKit\DefaultCrud\DefaultCrudRegistry;
use Nktlksvch\BulbaKit\Modules\ModuleRegistry;
use Nktlksvch\BulbaKit\Modules\Redirects\RedirectsModule;
use Nktlksvch\BulbaKit\Modules\WebsiteSettings\WebsiteSettingsModule;

/**
 * BulbaKit service provider.
 *
 * Registers two separate registries:
 * - DefaultCrudRegistry — standard CRUD resources installed automatically
 * - ModuleRegistry — optional modules selected by the user during install
 *
 * Auto-discovered via composer.json extra.laravel.providers.
 */
class BulbaKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/bulba.php', 'bulba');

        // Default CRUD resources — installed automatically during bulba:install.
        $this->app->singleton(DefaultCrudRegistry::class, function () {
            $registry = new DefaultCrudRegistry;

            return $registry;
        });

        // Optional modules — user selects which to install via multiselect.
        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry;
            $registry->register(new RedirectsModule);
            $registry->register(new WebsiteSettingsModule);

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
