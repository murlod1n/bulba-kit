<?php

namespace Nktlksvch\BulbaKit\Services\Install;

class BackendInstaller
{
    public function __construct(
        private readonly InstallHelper $helper,
    ) {}

    public function installPhpDependencies(): bool
    {
        $packages = [];
        if (! $this->helper->isPackageInstalled('inertiajs/inertia-laravel')) {
            $packages[] = 'inertiajs/inertia-laravel:^3.0';
        }
        if (! $this->helper->isPackageInstalled('laravel/wayfinder')) {
            $packages[] = 'laravel/wayfinder:^0.1.14';
        }
        if (! $this->helper->isPackageInstalled('laravel/fortify')) {
            $packages[] = 'laravel/fortify:^1.37.2';
        }
        if (! $this->helper->isPackageInstalled('spatie/laravel-medialibrary')) {
            $packages[] = 'spatie/laravel-medialibrary:^11.0';
        }
        if (! $this->helper->isPackageInstalled('spatie/laravel-settings')) {
            $packages[] = 'spatie/laravel-settings:^3.0';
        }
        if (! $this->helper->isPackageInstalled('spatie/laravel-translatable')) {
            $packages[] = 'spatie/laravel-translatable:^6.0';
        }

        $devPackages = [];
        if (! $this->helper->isPackageInstalled('larastan/larastan')) {
            $devPackages[] = 'larastan/larastan:^3.9';
        }

        if (empty($packages) && empty($devPackages)) {
            return false;
        }

        if (! empty($packages)) {
            $this->helper->executeCommand('composer require '.implode(' ', $packages).' -W --no-interaction');
        }

        if (! empty($devPackages)) {
            $this->helper->executeCommand('composer require --dev '.implode(' ', $devPackages).' -W --no-interaction');
        }

        if ($this->helper->isPackageInstalled('spatie/laravel-medialibrary')) {
            $this->helper->executeCommand('php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"');
        }

        if ($this->helper->isPackageInstalled('spatie/laravel-settings')) {
            $this->helper->executeCommand('php artisan vendor:publish --provider="Spatie\\LaravelSettings\\LaravelSettingsServiceProvider" --tag="settings-config"');
            $this->helper->executeCommand('php artisan vendor:publish --provider="Spatie\\LaravelSettings\\LaravelSettingsServiceProvider" --tag="settings-migrations"');
        }

        return true;
    }

    public function createBackend(): void
    {
        // Providers
        $this->helper->copyStubIfNotExists('php/Providers/AppServiceProvider.php.stub', app_path('Providers/AppServiceProvider.php'));
        $this->helper->copyStubIfNotExists('php/Providers/FortifyServiceProvider.php.stub', app_path('Providers/FortifyServiceProvider.php'));

        // Controllers
        $this->helper->copyStubIfNotExists('php/Controllers/Settings/ProfileController.php.stub', app_path('Http/Controllers/Settings/ProfileController.php'));
        $this->helper->copyStubIfNotExists('php/Controllers/Settings/SecurityController.php.stub', app_path('Http/Controllers/Settings/SecurityController.php'));

        // Actions
        $this->helper->copyStubIfNotExists('php/Actions/CreateNewUser.php.stub', app_path('Actions/Fortify/CreateNewUser.php'));
        $this->helper->copyStubIfNotExists('php/Actions/ResetUserPassword.php.stub', app_path('Actions/Fortify/ResetUserPassword.php'));

        // Concerns
        $this->helper->copyStubIfNotExists('php/Concerns/PasswordValidationRules.php.stub', app_path('Concerns/PasswordValidationRules.php'));
        $this->helper->copyStubIfNotExists('php/Concerns/ProfileValidationRules.php.stub', app_path('Concerns/ProfileValidationRules.php'));

        // Middleware
        $this->helper->copyStubIfNotExists('php/Middleware/HandleAppearance.php.stub', app_path('Http/Middleware/HandleAppearance.php'));
        $this->helper->copyStubIfNotExists('php/Middleware/HandleInertiaRequests.php.stub', app_path('Http/Middleware/HandleInertiaRequests.php'));

        // Requests
        $requests = [
            'ProfileUpdateRequest.php', 'ProfileDeleteRequest.php',
            'PasswordUpdateRequest.php', 'TwoFactorAuthenticationRequest.php',
        ];
        foreach ($requests as $request) {
            $this->helper->copyStubIfNotExists(
                "php/Requests/{$request}.stub",
                app_path("Http/Requests/Settings/{$request}")
            );
        }

        // Routes (always overwrite)
        $this->helper->copyStub('php/routes/settings.php.stub', base_path('routes/settings.php'));
        $this->helper->copyStub('php/routes/web.php.stub', base_path('routes/web.php'));

        // Bootstrap files (always overwrite)
        $this->helper->copyStub('php/bootstrap/app.php.stub', base_path('bootstrap/app.php'));
        $this->helper->copyStub('php/bootstrap/providers.php.stub', base_path('bootstrap/providers.php'));
    }

    public function createBladeTemplate(): void
    {
        $this->helper->copyStubIfNotExists('views/app.blade.php.stub', resource_path('views/app.blade.php'));
    }

    public function runWayfinder(): void
    {
        $this->helper->executeCommand('php artisan wayfinder:generate --with-form');
    }

    public function cleanupOldFiles(): void
    {
        $filesToDelete = [
            resource_path('views/welcome.blade.php'),
            base_path('vite.config.js'),
            resource_path('js/app.js'),
        ];

        foreach ($filesToDelete as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Install HandleLocale middleware and register it in bootstrap/app.php.
     */
    public function installLocaleMiddleware(): void
    {
        $destination = app_path('Http/Middleware/HandleLocale.php');

        if (! file_exists($destination)) {
            $this->helper->copyStub('php/Middleware/HandleLocale.php.stub', $destination);
        }

        // Register in bootstrap/app.php
        $bootstrapPath = base_path('bootstrap/app.php');
        if (! file_exists($bootstrapPath)) {
            return;
        }

        $content = file_get_contents($bootstrapPath);

        if (str_contains($content, 'HandleLocale')) {
            return;
        }

        $appendToWeb = "        \$middleware->appendToGroup('web', \\App\\Http\\Middleware\\HandleLocale::class);";

        if (str_contains($content, 'withMiddleware')) {
            $content = preg_replace(
                '/(->withMiddleware\(function \(Middleware \$middleware\) \{)/',
                "$1\n{$appendToWeb}",
                $content,
                1
            );
        }

        file_put_contents($bootstrapPath, $content);
    }
}
