<?php

namespace Nktlksvch\BulbaKit\Modules\WebsiteSettings;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;

/**
 * Website Settings module — typed settings management via spatie/laravel-settings.
 *
 * Uses typed Settings classes (GeneralSettings, SeoSettings) instead of
 * a generic key-value Eloquent model. Settings are stored in the database
 * repository and managed through spatie's migration system.
 *
 * Route: /admin/website-settings
 */
class WebsiteSettingsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Website Settings';
    }

    public function description(): string
    {
        return 'Website settings management using typed settings classes (spatie/laravel-settings)';
    }

    public function icon(): string
    {
        return 'settings';
    }

    /**
     * Install the website settings module: settings classes, migrations,
     * controller, routes, and custom index page.
     */
    public function install(object $command): void
    {
        $command->info('  Publishing spatie/laravel-settings config and migrations...');
        $this->publishSettingsDependencies($command);

        $command->info('  Creating settings classes...');
        $this->installSettingsClasses($command);

        $command->info('  Creating settings migrations...');
        $this->installSettingsMigrations($command);

        $command->info('  Registering settings classes...');
        $this->registerSettingsClasses($command);

        $command->info('  Generating controller...');
        $this->installController($command);

        $command->info('  Generating routes...');
        $this->installRoutes($command);

        $command->info('  Generating pages...');
        $this->installPages($command);
    }

    /**
     * @return array<int, array{group: string, items: array<int, array{title: string, href: string, icon: string}>}>
     */
    public function navigation(): array
    {
        return [
            ['group' => 'Settings', 'items' => [
                ['title' => 'Website Settings', 'href' => '/admin/website-settings', 'icon' => 'settings'],
            ]],
        ];
    }

    /**
     * Publish spatie/laravel-settings config and the create_settings_table migration.
     *
     * The settings table migration must exist in database/migrations/ so that
     * `php artisan migrate` creates the table before settings migrations try to use it.
     */
    protected function publishSettingsDependencies(object $command): void
    {
        // Publish config/settings.php
        if (! File::exists(config_path('settings.php'))) {
            Artisan::call('vendor:publish', [
                '--provider' => 'Spatie\\LaravelSettings\\LaravelSettingsServiceProvider',
                '--tag' => 'settings-config',
            ]);
            $command->info('    Published config/settings.php');
        } else {
            $command->info('    config/settings.php already exists, skipping.');
        }

        // Publish create_settings_table migration to database/migrations/
        $this->publishSettingsTableMigration($command);
    }

    /**
     * Publish the create_settings_table migration from the spatie package.
     *
     * Uses an early timestamp so it runs before other migrations.
     * If the migration already exists (by class name check), skips.
     */
    protected function publishSettingsTableMigration(object $command): void
    {
        $migrationsDir = database_path('migrations');

        // Check if migration already exists by scanning for the class name
        $existingFiles = File::glob($migrationsDir.'/*_create_settings_table.php');
        if (! empty($existingFiles)) {
            $command->info('    create_settings_table migration already exists, skipping.');

            return;
        }

        // Use the spatie package's published migration stub, or fall back to our copy
        $vendorStub = base_path('vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub');
        $moduleStub = dirname(__DIR__).'/WebsiteSettings/stubs/create_settings_table.stub';

        $stubPath = File::exists($vendorStub) ? $vendorStub : $moduleStub;

        if (! File::exists($stubPath)) {
            $command->info('    create_settings_table stub not found, skipping.');

            return;
        }

        File::ensureDirectoryExists($migrationsDir);

        // Use early timestamp so this migration runs before other migrations
        $filename = '2022_12_14_083707_create_settings_table.php';
        File::put($migrationsDir.'/'.$filename, File::get($stubPath));
        $command->info('    Created: database/migrations/'.$filename);
    }

    /**
     * Copy settings class stubs to app/Settings/Website/.
     */
    protected function installSettingsClasses(object $command): void
    {
        $settingsDir = app_path('Settings/Website');
        File::ensureDirectoryExists($settingsDir);

        $stubsDir = dirname(__DIR__).'/WebsiteSettings/stubs';

        $classes = [
            'GeneralSettings.php' => 'general-settings.stub',
            'SeoSettings.php' => 'seo-settings.stub',
        ];

        foreach ($classes as $filename => $stub) {
            $destination = $settingsDir.'/'.$filename;

            if (File::exists($destination)) {
                $command->info("    {$filename} already exists, skipping.");

                continue;
            }

            File::put($destination, File::get($stubsDir.'/'.$stub));
            $command->info("    Created: app/Settings/Website/{$filename}");
        }
    }

    /**
     * Copy settings migration stubs to database/settings/.
     */
    protected function installSettingsMigrations(object $command): void
    {
        $migrationsDir = database_path('settings');
        File::ensureDirectoryExists($migrationsDir);

        $stubsDir = dirname(__DIR__).'/WebsiteSettings/stubs';

        $timestamp = date('Y_m_d_His');
        $nextTimestamp = date('Y_m_d_His', time() + 1);
        $migrations = [
            "{$timestamp}_create_general_settings.php" => 'general-settings-migration.stub',
            "{$nextTimestamp}_create_seo_settings.php" => 'seo-settings-migration.stub',
        ];

        foreach ($migrations as $filename => $stub) {
            $destination = $migrationsDir.'/'.$filename;

            if (File::exists($destination)) {
                continue;
            }

            File::put($destination, File::get($stubsDir.'/'.$stub));
            $command->info("    Created: database/settings/{$filename}");
        }
    }

    /**
     * Register settings classes in config/settings.php.
     */
    protected function registerSettingsClasses(object $command): void
    {
        $configPath = config_path('settings.php');

        if (! File::exists($configPath)) {
            $command->info('    config/settings.php not found, skipping registration.');

            return;
        }

        $content = File::get($configPath);

        $classes = [
            'App\\Settings\\Website\\GeneralSettings',
            'App\\Settings\\Website\\SeoSettings',
        ];

        foreach ($classes as $class) {
            if (str_contains($content, $class)) {
                continue;
            }

            $content = preg_replace(
                "/'settings' => \[\s*/",
                "'settings' => [\n        {$class}::class,",
                $content,
                1
            );
        }

        File::put($configPath, $content);
        $command->info('    Registered settings classes in config/settings.php');
    }

    /**
     * Copy controller stub to the host app.
     */
    protected function installController(object $command): void
    {
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $pagesPath = config('bulba.react_pages_path', 'admin');

        $controllerPath = app_path(
            str_replace('\\', '/', str_replace('App\\', '', $namespace)).'/WebsiteSettingController.php'
        );

        if (File::exists($controllerPath)) {
            $command->info('    Controller already exists, skipping.');

            return;
        }

        $stubsDir = dirname(__DIR__).'/WebsiteSettings/stubs';
        $content = File::get($stubsDir.'/website-settings-controller.stub');

        $content = str_replace(
            ['{{ namespace }}', '{{ pagesPath }}'],
            [$namespace, $pagesPath],
            $content
        );

        File::ensureDirectoryExists(dirname($controllerPath));
        File::put($controllerPath, $content);
        $command->info('    Created: WebsiteSettingController.php');
    }

    /**
     * Append routes to routes/admin.php.
     */
    protected function installRoutes(object $command): void
    {
        $routeFile = base_path('routes/admin.php');

        if (! File::exists($routeFile)) {
            $command->info('    routes/admin.php not found, skipping route registration.');

            return;
        }

        $content = File::get($routeFile);

        if (str_contains($content, 'WebsiteSettingController')) {
            $command->info('    Routes already registered, skipping.');

            return;
        }

        // Add use statement
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $useStatement = "use {$namespace}\\WebsiteSettingController;";

        if (! str_contains($content, $useStatement)) {
            $lastUsePos = strrpos($content, 'use ');
            if ($lastUsePos !== false) {
                $endOfLine = strpos($content, "\n", $lastUsePos);
                $content = substr_replace($content, "\n".$useStatement, $endOfLine, 0);
            } else {
                $content = str_replace('<?php', "<?php\n\n{$useStatement}", $content);
            }
        }

        // Add route definitions
        $stubsDir = dirname(__DIR__).'/WebsiteSettings/stubs';
        $routes = File::get($stubsDir.'/website-settings-routes.stub');

        $content = rtrim($content)."\n\n".$routes;

        File::put($routeFile, $content);
        $command->info('    Routes added to routes/admin.php');
    }

    /**
     * Copy the custom index page stub to the host app.
     */
    protected function installPages(object $command): void
    {
        $pagesPath = config('bulba.react_pages_path', 'admin');
        $pagesDir = resource_path("js/pages/{$pagesPath}/WebsiteSetting");

        File::ensureDirectoryExists($pagesDir);

        $stubsDir = dirname(__DIR__).'/WebsiteSettings/stubs';
        $destination = $pagesDir.'/Index.tsx';

        if (File::exists($destination)) {
            $command->info('    Index.tsx already exists, skipping.');

            return;
        }

        File::put($destination, File::get($stubsDir.'/website-settings-index-page.stub'));
        $command->info('    Created: Index.tsx');
    }
}
