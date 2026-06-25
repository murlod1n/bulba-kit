<?php

namespace Nktlksvch\BulbaKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class BulbaInstallCommand extends Command
{
    protected $signature = 'bulba:install {--force : Overwrite existing files}';
    protected $description = 'Install the complete BulbaKit React admin panel infrastructure';

    protected string $stubsPath;
    protected int $step = 0;
    protected int $totalSteps = 11;

    public function handle(): void
    {
        $this->stubsPath = dirname(__DIR__, 2) . '/Resources/install-stubs';

        info('🚀 BulbaKit Installer');
        note('Setting up React + Inertia + Tailwind + shadcn/ui admin panel...');
        $this->newLine();

        $this->installPhpDependencies();
        $this->installNpmDependencies();
        $this->createConfigFiles();
        $this->createCss();
        $this->initShadcn();
        $this->createBladeTemplate();
        $this->createJsInfrastructure();
        $this->createComponents();
        $this->createPages();
        $this->createBackend();
        $this->runWayfinder();

        $this->cleanupOldFiles();

        $this->newLine();
        info('✅ BulbaKit installed successfully!');
        $this->newLine();
        note('Next steps:');
        note('  1. npm run build (or npm run dev)');
        note('  2. php artisan migrate');
        note('  3. Configure .env (APP_NAME, DB, MAIL, etc.)');
        note('  4. php artisan bulba:make to create CRUD resources');
    }

    protected function installPhpDependencies(): void
    {
        $this->step('Installing PHP dependencies');

        $packages = [];
        if (!$this->isPackageInstalled('inertiajs/inertia-laravel')) {
            $packages[] = 'inertiajs/inertia-laravel:^3.0';
        }
        if (!$this->isPackageInstalled('laravel/wayfinder')) {
            $packages[] = 'laravel/wayfinder:^0.1.14';
        }
        if (!$this->isPackageInstalled('laravel/fortify')) {
            $packages[] = 'laravel/fortify:^1.37.2';
        }

        $devPackages = [];
        if (!$this->isPackageInstalled('larastan/larastan')) {
            $devPackages[] = 'larastan/larastan:^3.9';
        }

        if (empty($packages) && empty($devPackages)) {
            note('  All PHP dependencies already installed.');
            return;
        }

        if (!empty($packages)) {
            note('  Installing: ' . implode(', ', $packages));
            $this->executeCommand('composer require ' . implode(' ', $packages) . ' -W --no-interaction');
        }

        if (!empty($devPackages)) {
            note('  Installing dev: ' . implode(', ', $devPackages));
            $this->executeCommand('composer require --dev ' . implode(' ', $devPackages) . ' -W --no-interaction');
        }
    }

    protected function installNpmDependencies(): void
    {
        $this->step('Installing NPM dependencies');

        $packageJsonPath = base_path('package.json');
        if (File::exists($packageJsonPath)) {
            $packageJson = json_decode(File::get($packageJsonPath), true);
            if (isset($packageJson['dependencies']['react'])) {
                note('  NPM dependencies already installed.');
                return;
            }
        }

        $packages = [
            'react', 'react-dom', '@inertiajs/react', '@inertiajs/vite',
            'tailwindcss', '@tailwindcss/vite', 'tw-animate-css',
            '@vitejs/plugin-react', 'laravel-vite-plugin', 'vite', 'typescript',
            'clsx', 'tailwind-merge', 'class-variance-authority',
            'lucide-react', 'sonner', 'input-otp',
            'babel-plugin-react-compiler',
            '@laravel/passkeys',
            '@base-ui/react',
        ];

        $devPackages = [
            '@types/react', '@types/react-dom', '@types/node',
            '@laravel/vite-plugin-wayfinder',
            'globals', 'concurrently',
            '@eslint/js', '@stylistic/eslint-plugin',
            'eslint', 'eslint-config-prettier', 'eslint-import-resolver-typescript',
            'eslint-plugin-import', 'eslint-plugin-react', 'eslint-plugin-react-hooks',
            'prettier', 'prettier-plugin-tailwindcss',
            'typescript-eslint',
        ];

        note('  Installing production dependencies...');
        $this->executeCommand('npm install ' . implode(' ', $packages));

        note('  Installing dev dependencies...');
        $this->executeCommand('npm install -D ' . implode(' ', $devPackages));
    }

    protected function initShadcn(): void
    {
        $this->step('Initializing shadcn/ui');

        $componentsJsonPath = base_path('components.json');
        if (File::exists($componentsJsonPath) && !$this->option('force')) {
            note('  components.json already exists, skipping shadcn init.');
        } else {
            if (File::exists($componentsJsonPath)) {
                File::delete($componentsJsonPath);
            }

            note('  Running shadcn init...');
            $this->executeCommand('npx shadcn@latest init --base base --yes');
        }

        $components = [
            'button', 'card', 'input', 'label', 'select', 'separator', 'badge',
            'sidebar', 'skeleton', 'avatar', 'dropdown-menu', 'dialog', 'tooltip',
            'toggle', 'toggle-group', 'collapsible', 'navigation-menu', 'breadcrumb', 'sheet',
            'checkbox', 'input-otp', 'sonner', 'spinner', 'alert',
            'icon', 'placeholder-pattern',
        ];

        note('  Installing ' . count($components) . ' shadcn components...');
        $failed = [];

        foreach ($components as $component) {
            $this->line("    Installing: {$component}");
            $result = $this->executeCommand("npx shadcn@latest add {$component} --yes");
            if (!$result) {
                $this->line("    Retrying: {$component}");
                sleep(2);
                $result = $this->executeCommand("npx shadcn@latest add {$component} --yes");
                if (!$result) {
                    $failed[] = $component;
                    $this->warn("    Failed: {$component}");
                }
            }
        }

        if (!empty($failed)) {
            warning('  Failed to install: ' . implode(', ', $failed));
        }
    }

    protected function createConfigFiles(): void
    {
        $this->step('Creating configuration files');

        $this->copyStubIfNotExists('config/tsconfig.json.stub', base_path('tsconfig.json'));
        $this->copyStubIfNotExists('config/vite.config.ts.stub', base_path('vite.config.ts'));

        // Config files (always overwrite)
        $this->copyStub('config/.npmrc.stub', base_path('.npmrc'));
        $this->copyStub('config/.prettierignore.stub', base_path('.prettierignore'));
        $this->copyStub('config/.prettierrc.stub', base_path('.prettierrc'));
        $this->copyStub('config/eslint.config.js.stub', base_path('eslint.config.js'));
        $this->copyStub('config/phpstan.neon.stub', base_path('phpstan.neon'));
        $this->copyStub('config/pint.json.stub', base_path('pint.json'));
        $this->copyStub('config/pnpm-workspace.yaml.stub', base_path('pnpm-workspace.yaml'));

        $this->updateEnvExample();
    }

    protected function createCss(): void
    {
        $this->step('Creating CSS with Tailwind v4');

        $this->copyStubIfNotExists('css/app.css.stub', resource_path('css/app.css'));
    }

    protected function createBladeTemplate(): void
    {
        $this->step('Creating Blade template');

        $this->copyStubIfNotExists('views/app.blade.php.stub', resource_path('views/app.blade.php'));
    }

    protected function createJsInfrastructure(): void
    {
        $this->step('Creating JS infrastructure');

        $this->copyStubIfNotExists('js/app.tsx.stub', resource_path('js/app.tsx'));
        $this->copyStubIfNotExists('js/lib/utils.ts.stub', resource_path('js/lib/utils.ts'));

        $types = ['index.ts', 'auth.ts', 'navigation.ts', 'ui.ts', 'global.d.ts', 'vite-env.d.ts'];
        foreach ($types as $type) {
            $this->copyStubIfNotExists("js/types/{$type}.stub", resource_path("js/types/{$type}"));
        }

        $hooks = [
            'use-appearance.tsx', 'use-clipboard.ts', 'use-current-url.ts',
            'use-flash-toast.ts', 'use-initials.tsx', 'use-mobile.tsx',
            'use-mobile-navigation.ts', 'use-two-factor-auth.ts',
        ];
        foreach ($hooks as $hook) {
            $this->copyStubIfNotExists("js/hooks/{$hook}.stub", resource_path("js/hooks/{$hook}"));
        }
    }

    protected function createComponents(): void
    {
        $this->step('Creating React components');

        // Layouts
        $this->copyStubIfNotExists('js/layouts/app-layout.tsx.stub', resource_path('js/layouts/app-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/auth-layout.tsx.stub', resource_path('js/layouts/auth-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/auth/auth-simple-layout.tsx.stub', resource_path('js/layouts/auth/auth-simple-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/auth/auth-card-layout.tsx.stub', resource_path('js/layouts/auth/auth-card-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/auth/auth-split-layout.tsx.stub', resource_path('js/layouts/auth/auth-split-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/app/app-sidebar-layout.tsx.stub', resource_path('js/layouts/app/app-sidebar-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/app/app-header-layout.tsx.stub', resource_path('js/layouts/app/app-header-layout.tsx'));
        $this->copyStubIfNotExists('js/layouts/settings/layout.tsx.stub', resource_path('js/layouts/settings/layout.tsx'));

        // Components
        $components = [
            'alert-error.tsx', 'app-content.tsx', 'app-header.tsx',
            'app-logo-icon.tsx', 'app-logo.tsx', 'app-shell.tsx',
            'app-sidebar-header.tsx', 'app-sidebar.tsx', 'appearance-tabs.tsx',
            'breadcrumbs.tsx', 'delete-user.tsx', 'heading.tsx',
            'input-error.tsx', 'nav-footer.tsx', 'nav-main.tsx',
            'nav-user.tsx', 'password-input.tsx', 'text-link.tsx',
            'user-info.tsx', 'user-menu-content.tsx',
            'manage-passkeys.tsx', 'manage-two-factor.tsx',
            'passkey-item.tsx', 'passkey-register.tsx', 'passkey-verify.tsx',
            'two-factor-recovery-codes.tsx', 'two-factor-setup-modal.tsx',
        ];
        foreach ($components as $component) {
            $this->copyStubIfNotExists(
                "js/components/{$component}.stub",
                resource_path("js/components/{$component}")
            );
        }
    }

    protected function createPages(): void
    {
        $this->step('Creating pages');

        // Main pages (dashboard + welcome)
        $this->copyStubIfNotExists('js/pages/dashboard.tsx.stub', resource_path('js/pages/dashboard.tsx'));
        $this->copyStubIfNotExists('js/pages/welcome.tsx.stub', resource_path('js/pages/welcome.tsx'));

        // Auth pages
        $authPages = [
            'login.tsx', 'register.tsx', 'forgot-password.tsx',
            'reset-password.tsx', 'verify-email.tsx',
            'two-factor-challenge.tsx', 'confirm-password.tsx',
        ];
        foreach ($authPages as $page) {
            $this->copyStubIfNotExists(
                "js/pages/auth/{$page}.stub",
                resource_path("js/pages/auth/{$page}")
            );
        }

        // Settings pages
        $settingsPages = ['profile.tsx', 'security.tsx', 'appearance.tsx'];
        foreach ($settingsPages as $page) {
            $this->copyStubIfNotExists(
                "js/pages/settings/{$page}.stub",
                resource_path("js/pages/settings/{$page}")
            );
        }
    }

    protected function createBackend(): void
    {
        $this->step('Creating backend infrastructure');

        // Providers
        $this->copyStubIfNotExists('php/Providers/AppServiceProvider.php.stub', app_path('Providers/AppServiceProvider.php'));
        $this->copyStubIfNotExists('php/Providers/FortifyServiceProvider.php.stub', app_path('Providers/FortifyServiceProvider.php'));

        // Controllers
        $this->copyStubIfNotExists('php/Controllers/Settings/ProfileController.php.stub', app_path('Http/Controllers/Settings/ProfileController.php'));
        $this->copyStubIfNotExists('php/Controllers/Settings/SecurityController.php.stub', app_path('Http/Controllers/Settings/SecurityController.php'));

        // Actions
        $this->copyStubIfNotExists('php/Actions/CreateNewUser.php.stub', app_path('Actions/Fortify/CreateNewUser.php'));
        $this->copyStubIfNotExists('php/Actions/ResetUserPassword.php.stub', app_path('Actions/Fortify/ResetUserPassword.php'));

        // Concerns
        $this->copyStubIfNotExists('php/Concerns/PasswordValidationRules.php.stub', app_path('Concerns/PasswordValidationRules.php'));
        $this->copyStubIfNotExists('php/Concerns/ProfileValidationRules.php.stub', app_path('Concerns/ProfileValidationRules.php'));

        // Middleware
        $this->copyStubIfNotExists('php/Middleware/HandleAppearance.php.stub', app_path('Http/Middleware/HandleAppearance.php'));
        $this->copyStubIfNotExists('php/Middleware/HandleInertiaRequests.php.stub', app_path('Http/Middleware/HandleInertiaRequests.php'));

        // Requests
        $requests = [
            'ProfileUpdateRequest.php', 'ProfileDeleteRequest.php',
            'PasswordUpdateRequest.php', 'TwoFactorAuthenticationRequest.php',
        ];
        foreach ($requests as $request) {
            $this->copyStubIfNotExists(
                "php/Requests/{$request}.stub",
                app_path("Http/Requests/Settings/{$request}")
            );
        }

        // Routes (always overwrite)
        $this->copyStub('php/routes/settings.php.stub', base_path('routes/settings.php'));
        $this->copyStub('php/routes/web.php.stub', base_path('routes/web.php'));

        // Bootstrap files (always overwrite)
        $this->copyStub('php/bootstrap/app.php.stub', base_path('bootstrap/app.php'));
        $this->copyStub('php/bootstrap/providers.php.stub', base_path('bootstrap/providers.php'));
    }

    protected function runWayfinder(): void
    {
        $this->step('Generating Wayfinder routes');

        $this->executeCommand('php artisan wayfinder:generate --with-form');
    }

    protected function cleanupOldFiles(): void
    {
        $welcomePath = resource_path('views/welcome.blade.php');
        if (File::exists($welcomePath)) {
            File::delete($welcomePath);
            note('  Deleted: resources/views/welcome.blade.php');
        }
    }

    protected function updateEnvExample(): void
    {
        $envExamplePath = base_path('.env.example');
        if (!File::exists($envExamplePath)) {
            return;
        }

        $content = File::get($envExamplePath);
        if (!str_contains($content, 'VITE_APP_NAME')) {
            $content .= "\nVITE_APP_NAME=\"\${APP_NAME}\"\n";
            File::put($envExamplePath, $content);
            note('  Added VITE_APP_NAME to .env.example');
        }
    }

    protected function step(string $message): void
    {
        $this->step++;
        $this->newLine();
        $this->info("Step {$this->step}/{$this->totalSteps}: {$message}");
    }

    protected function copyStub(string $stubPath, string $destination): void
    {
        $fullStubPath = $this->stubsPath . '/' . $stubPath;

        if (!File::exists($fullStubPath)) {
            warning("  Stub not found: {$stubPath}");
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($fullStubPath, $destination);
        note("  Created: " . str_replace(base_path() . '/', '', $destination));
    }

    protected function copyStubIfNotExists(string $stubPath, string $destination): void
    {
        if (File::exists($destination) && !$this->option('force')) {
            note("  Skipped (exists): " . str_replace(base_path() . '/', '', $destination));
            return;
        }

        $this->copyStub($stubPath, $destination);
    }

    protected function isPackageInstalled(string $package): bool
    {
        $composerLockPath = base_path('composer.lock');
        if (!File::exists($composerLockPath)) {
            return false;
        }

        $lock = json_decode(File::get($composerLockPath), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        foreach ($packages as $installed) {
            if ($installed['name'] === $package) {
                return true;
            }
        }

        return false;
    }

    protected function executeCommand(string $command): bool
    {
        $process = \Symfony\Component\Process\Process::fromShellCommandline(
            $command,
            base_path(),
            ['PATH' => getenv('PATH')],
            null,
            300
        );

        $process->run(function ($type, $buffer) {
            if ($this->getOutput()->isVerbose()) {
                $this->line($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            warning("  Command failed: {$command}");
            $errorOutput = $process->getErrorOutput();
            if (!empty($errorOutput)) {
                warning("  Error: " . substr($errorOutput, 0, 500));
            }
            return false;
        }

        return true;
    }
}
