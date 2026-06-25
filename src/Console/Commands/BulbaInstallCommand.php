<?php

namespace Nktlksvch\BulbaKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Generators\ReactPageGenerator;
use Nktlksvch\BulbaKit\Generators\ResourceGenerator;
use Nktlksvch\BulbaKit\Generators\RouteGenerator;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;
use Nktlksvch\BulbaKit\Modules\ModuleRegistry;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class BulbaInstallCommand extends Command
{
    protected $signature = 'bulba:install {--force : Overwrite existing files}';

    protected $description = 'Install the complete BulbaKit React admin panel infrastructure';

    protected string $stubsPath;

    protected int $step = 0;

    protected int $totalSteps = 13;

    public function handle(): void
    {
        $this->stubsPath = dirname(__DIR__, 2).'/Resources/install-stubs';

        info('BulbaKit Installer');
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

        // Module installation
        $this->newLine();
        $this->newLine();
        info('Module Installation');
        $this->newLine();
        $selectedModules = $this->selectModules();
        $this->installModules($selectedModules);

        // Wayfinder must run after all routes are registered
        $this->runWayfinder();

        $this->cleanupOldFiles();

        $this->newLine();
        info('BulbaKit installed successfully!');
        $this->newLine();
        note('Next steps:');
        note('  1. npm run build (or npm run dev)');
        note('  2. php artisan migrate');
        note('  3. Configure .env (APP_NAME, DB, MAIL, etc.)');
        note('  4. php artisan bulba:make to create CRUD resources');
    }

    /**
     * @return array<int, ModuleInterface>
     */
    protected function selectModules(): array
    {
        $this->step('Selecting modules');

        $registry = app(ModuleRegistry::class);
        $options = [];
        foreach ($registry->all() as $name => $module) {
            $options[$name] = "{$module->description()}";
        }

        $selected = multiselect(
            label: 'Which modules would you like to install?',
            options: $options,
            default: ['Website Settings'],
        );

        return array_map(fn ($name) => $registry->get($name), array_filter($selected));
    }

    /**
     * @param  array<int, ModuleInterface>  $modules
     */
    protected function installModules(array $modules): void
    {
        $this->step('Installing modules');

        foreach ($modules as $module) {
            $this->newLine();
            $this->info("  Installing: {$module->name()}");

            $this->installModuleMigration($module);
            $this->installModuleModel($module);
            $this->installModuleResource($module);
            $this->installModuleController($module);
            $this->installModulePages($module);
            $this->installModuleRoutes($module);
            $this->installModuleSeeder($module);

            $module->postInstall($this);

            note("  ✓ {$module->name()} installed");
        }

        // Generate navigation config
        $this->generateNavigation($modules);

        // Record installed modules in config
        $this->recordInstalledModules($modules);
    }

    protected function installModuleMigration(ModuleInterface $module): void
    {
        app(MigrationGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            [],
            $module->options()['timestamps'] ?? true,
            $module->options()['softDeletes'] ?? false,
            []
        );
    }

    protected function installModuleModel(ModuleInterface $module): void
    {
        app(ModelGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            $module->options()['softDeletes'] ?? false,
            []
        );
    }

    protected function installModuleResource(ModuleInterface $module): void
    {
        app(ResourceGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            []
        );
    }

    protected function installModuleController(ModuleInterface $module): void
    {
        $customStubs = $module->customStubs();

        // If custom index method exists, exclude 'index' from standard generation
        $methods = $module->controllerMethods();
        if (isset($customStubs['controller-index'])) {
            $methods = array_diff($methods, ['index']);
        }

        // Generate standard controller
        app(ControllerGenerator::class)->generate(
            $module->modelName(),
            'inertia',
            $methods,
            $module->fields()
        );

        // Append custom index method if defined
        if (isset($customStubs['controller-index'])) {
            $this->appendControllerMethod($module, $customStubs['controller-index']);
        }

        // Append custom methods if defined
        if (isset($customStubs['controller-method'])) {
            $this->appendControllerMethod($module, $customStubs['controller-method']);
        }
    }

    protected function installModulePages(ModuleInterface $module): void
    {
        $customStubs = $module->customStubs();
        $pagesPath = config('bulba.react_pages_path', 'admin');
        $pagesDir = resource_path("js/pages/{$pagesPath}/{$module->modelName()}");

        File::ensureDirectoryExists($pagesDir);

        // Install custom index page if defined
        if (isset($customStubs['index-page'])) {
            $stubPath = $this->getModuleStubPath($module, $customStubs['index-page']);
            if (File::exists($stubPath)) {
                File::put($pagesDir.'/Index.tsx', File::get($stubPath));
                note('  Created: '.str_replace(base_path().'/', '', $pagesDir.'/Index.tsx'));
            }
        } else {
            // Generate standard pages via ReactPageGenerator
            app(ReactPageGenerator::class)->generate(
                $module->modelName(),
                $module->fields(),
                []
            );
        }
    }

    protected function installModuleRoutes(ModuleInterface $module): void
    {
        app(RouteGenerator::class)->generate(
            $module->modelName(),
            'inertia',
            $module->controllerMethods()
        );
    }

    protected function installModuleSeeder(ModuleInterface $module): void
    {
        if (! $module->seederClass() || ! $module->seederStub()) {
            return;
        }

        $stubPath = $this->getModuleStubPath($module, $module->seederStub());
        $destination = database_path('seeders/'.$module->seederClass().'.php');

        if (File::exists($destination)) {
            note('  Skipped (exists): '.str_replace(base_path().'/', '', $destination));

            return;
        }

        if (File::exists($stubPath)) {
            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, File::get($stubPath));
            note('  Created: '.str_replace(base_path().'/', '', $destination));
        }
    }

    protected function appendControllerMethod(ModuleInterface $module, string $stubName): void
    {
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $controllerPath = app_path(
            str_replace('\\', '/', str_replace('App\\', '', $namespace))."/{$module->modelName()}Controller.php"
        );

        if (! File::exists($controllerPath)) {
            return;
        }

        $stubPath = $this->getModuleStubPath($module, $stubName);
        if (! File::exists($stubPath)) {
            return;
        }

        $content = File::get($controllerPath);
        $methodStub = File::get($stubPath);

        // Extract method name from stub and skip if already exists
        if (preg_match('/public function (\w+)\(/', $methodStub, $matches)) {
            $methodName = $matches[1];
            if (str_contains($content, "public function {$methodName}(")) {
                note("  Skipped (exists): {$methodName}()");

                return;
            }
        }

        // Add Request import if not present
        if (! str_contains($content, 'use Illuminate\\Http\\Request;')) {
            $content = str_replace(
                'use Inertia\\Inertia;',
                "use Illuminate\\Http\\Request;\nuse Inertia\\Inertia;",
                $content
            );
        }

        // Add model import for the method (e.g., Setting)
        $modelImport = "use App\\Models\\{$module->modelName()};";
        if (! str_contains($content, $modelImport)) {
            $content = str_replace(
                'use Inertia\\Inertia;',
                "{$modelImport}\nuse Inertia\\Inertia;",
                $content
            );
        }

        // Insert method before the closing brace
        $pagesPath = config('bulba.react_pages_path', 'admin');
        $methodStub = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ pagesPath }}'],
            [$module->modelName(), Str::lower($module->modelName()), $pagesPath],
            $methodStub
        );

        $content = preg_replace('/\}\s*$/', $methodStub."\n}", $content);

        File::put($controllerPath, $content);
    }

    /**
     * @param  array<int, ModuleInterface>  $modules
     */
    protected function generateNavigation(array $modules): void
    {
        $groups = [
            ['label' => 'General', 'items' => [
                ['title' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => 'layout-grid'],
            ]],
        ];

        foreach ($modules as $module) {
            foreach ($module->navigation() as $nav) {
                $groupLabel = $nav['group'];
                $found = false;

                foreach ($groups as &$g) {
                    if ($g['label'] === $groupLabel) {
                        $g['items'] = array_merge($g['items'], $nav['items']);
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    $groups[] = ['label' => $groupLabel, 'items' => $nav['items']];
                }
            }
        }

        $content = $this->renderNavigationTs($groups);
        $destination = resource_path('js/navigation.ts');

        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, $content);
        note('  Created: resources/js/navigation.ts');
    }

    /**
     * @param  array<int, array{label: string, items: array<int, array{title: string, href: string, icon: string}>}>  $groups
     */
    protected function renderNavigationTs(array $groups): string
    {
        $lines = [];
        $lines[] = "import type { NavGroup } from '@/types/navigation';";
        $lines[] = '';
        $lines[] = 'export const navigation: NavGroup[] = [';

        foreach ($groups as $group) {
            $lines[] = '    {';
            $lines[] = "        label: '{$group['label']}',";
            $lines[] = '        items: [';
            foreach ($group['items'] as $item) {
                $lines[] = "            { title: '{$item['title']}', href: '{$item['href']}', icon: '{$item['icon']}' },";
            }
            $lines[] = '        ],';
            $lines[] = '    },';
        }

        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, ModuleInterface>  $modules
     */
    protected function recordInstalledModules(array $modules): void
    {
        $names = array_map(fn ($m) => $m->name(), $modules);
        $configPath = config_path('bulba.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);

        if (str_contains($content, "'modules'")) {
            return;
        }

        $modulesList = implode(', ', array_map(fn ($n) => "'{$n}'", $names));
        $append = "\n    /*\n    |--------------------------------------------------------------------------\n    | Установленные модули\n    |--------------------------------------------------------------------------\n    */\n    'modules' => [{$modulesList}],\n";

        $content = preg_replace('/\];\s*$/', $append."\n];", $content);

        File::put($configPath, $content);
    }

    protected function getModuleStubPath(ModuleInterface $module, string $stubName): string
    {
        $reflection = new \ReflectionClass($module);
        $moduleDir = dirname($reflection->getFileName());

        return $moduleDir.'/stubs/'.$stubName.'.stub';
    }

    // --- Original methods below ---

    protected function installPhpDependencies(): void
    {
        $this->step('Installing PHP dependencies');

        $packages = [];
        if (! $this->isPackageInstalled('inertiajs/inertia-laravel')) {
            $packages[] = 'inertiajs/inertia-laravel:^3.0';
        }
        if (! $this->isPackageInstalled('laravel/wayfinder')) {
            $packages[] = 'laravel/wayfinder:^0.1.14';
        }
        if (! $this->isPackageInstalled('laravel/fortify')) {
            $packages[] = 'laravel/fortify:^1.37.2';
        }
        if (! $this->isPackageInstalled('spatie/laravel-medialibrary')) {
            $packages[] = 'spatie/laravel-medialibrary:^11.0';
        }

        $devPackages = [];
        if (! $this->isPackageInstalled('larastan/larastan')) {
            $devPackages[] = 'larastan/larastan:^3.9';
        }

        if (empty($packages) && empty($devPackages)) {
            note('  All PHP dependencies already installed.');

            return;
        }

        if (! empty($packages)) {
            note('  Installing: '.implode(', ', $packages));
            $this->executeCommand('composer require '.implode(' ', $packages).' -W --no-interaction');
        }

        if (! empty($devPackages)) {
            note('  Installing dev: '.implode(', ', $devPackages));
            $this->executeCommand('composer require --dev '.implode(' ', $devPackages).' -W --no-interaction');
        }

        // Publish spatie medialibrary migration
        if ($this->isPackageInstalled('spatie/laravel-medialibrary')) {
            $this->executeCommand('php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"');
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
            '@eslint/js@^9.0.0',
            '@stylistic/eslint-plugin@^4.0.0',
            'eslint@^9.0.0',
            'eslint-config-prettier@^10.0.0',
            'eslint-import-resolver-typescript@^3.7.0',
            'eslint-plugin-import@^2.31.0',
            'eslint-plugin-react@^7.37.0',
            'eslint-plugin-react-hooks@^5.0.0',
            'prettier', 'prettier-plugin-tailwindcss',
            'typescript-eslint@^8.0.0',
        ];

        note('  Installing production dependencies...');
        $this->executeCommand('npm install '.implode(' ', $packages));

        note('  Installing dev dependencies...');
        $this->executeCommand('npm install -D '.implode(' ', $devPackages));
    }

    protected function initShadcn(): void
    {
        $this->step('Initializing shadcn/ui');

        $componentsJsonPath = base_path('components.json');
        if (File::exists($componentsJsonPath) && ! $this->option('force')) {
            note('  components.json already exists, skipping shadcn init.');
        } else {
            if (File::exists($componentsJsonPath)) {
                File::delete($componentsJsonPath);
            }

            note('  Running shadcn init...');
            $this->executeCommand('npx shadcn@latest init --base base --preset b3lno3MAK --template next --yes --force');
        }

        $components = [
            'button', 'card', 'input', 'label', 'select', 'separator', 'badge',
            'sidebar', 'skeleton', 'avatar', 'dropdown-menu', 'dialog', 'tooltip',
            'toggle', 'toggle-group', 'collapsible', 'navigation-menu', 'breadcrumb', 'sheet',
            'checkbox', 'input-otp', 'sonner', 'spinner', 'alert', 'alert-dialog',
            'field', 'table', 'textarea', 'pagination',
        ];

        note('  Installing '.count($components).' shadcn components...');
        $failed = [];

        foreach ($components as $component) {
            $this->line("    Installing: {$component}");
            $result = $this->executeCommand("npx shadcn@latest add {$component} --yes");
            if (! $result) {
                $this->line("    Retrying: {$component}");
                sleep(2);
                $result = $this->executeCommand("npx shadcn@latest add {$component} --yes");
                if (! $result) {
                    $failed[] = $component;
                    $this->warn("    Failed: {$component}");
                }
            }
        }

        if (! empty($failed)) {
            warning('  Failed to install: '.implode(', ', $failed));
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

        // Only copy pnpm-workspace.yaml if pnpm is installed
        if ($this->isCommandAvailable('pnpm')) {
            $this->copyStub('config/pnpm-workspace.yaml.stub', base_path('pnpm-workspace.yaml'));
        }

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
        $this->copyStubIfNotExists('js/lib/icon-map.ts.stub', resource_path('js/lib/icon-map.ts'));

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
            'ui/image-upload.tsx',
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

        $adminPagesPath = 'js/pages/admin';

        // Main pages (dashboard + welcome)
        $this->copyStubIfNotExists('js/pages/dashboard.tsx.stub', resource_path("{$adminPagesPath}/dashboard.tsx"));
        $this->copyStubIfNotExists('js/pages/welcome.tsx.stub', resource_path("{$adminPagesPath}/welcome.tsx"));

        // Auth pages
        $authPages = [
            'login.tsx', 'register.tsx', 'forgot-password.tsx',
            'reset-password.tsx', 'verify-email.tsx',
            'two-factor-challenge.tsx', 'confirm-password.tsx',
        ];
        foreach ($authPages as $page) {
            $this->copyStubIfNotExists(
                "js/pages/auth/{$page}.stub",
                resource_path("{$adminPagesPath}/auth/{$page}")
            );
        }

        // Settings pages
        $settingsPages = ['profile.tsx', 'security.tsx', 'appearance.tsx'];
        foreach ($settingsPages as $page) {
            $this->copyStubIfNotExists(
                "js/pages/settings/{$page}.stub",
                resource_path("{$adminPagesPath}/settings/{$page}")
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
        $filesToDelete = [
            resource_path('views/welcome.blade.php'),
            base_path('vite.config.js'),
            resource_path('js/app.js'),
        ];

        foreach ($filesToDelete as $path) {
            if (File::exists($path)) {
                File::delete($path);
                note('  Deleted: '.str_replace(base_path().'/', '', $path));
            }
        }
    }

    protected function updateEnvExample(): void
    {
        $envExamplePath = base_path('.env.example');
        if (! File::exists($envExamplePath)) {
            return;
        }

        $content = File::get($envExamplePath);
        if (! str_contains($content, 'VITE_APP_NAME')) {
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

    public function copyStub(string $stubPath, string $destination): void
    {
        $fullStubPath = $this->stubsPath.'/'.$stubPath;

        if (! File::exists($fullStubPath)) {
            warning("  Stub not found: {$stubPath}");

            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($fullStubPath, $destination);
        note('  Created: '.str_replace(base_path().'/', '', $destination));
    }

    protected function copyStubIfNotExists(string $stubPath, string $destination): void
    {
        if (File::exists($destination) && ! $this->option('force')) {
            note('  Skipped (exists): '.str_replace(base_path().'/', '', $destination));

            return;
        }

        $this->copyStub($stubPath, $destination);
    }

    protected function isPackageInstalled(string $package): bool
    {
        $composerLockPath = base_path('composer.lock');
        if (! File::exists($composerLockPath)) {
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

    protected function isCommandAvailable(string $command): bool
    {
        $process = Process::fromShellCommandline(
            "which {$command}",
            base_path(),
            ['PATH' => getenv('PATH')],
            null,
            10
        );
        $process->run();

        return $process->isSuccessful();
    }

    protected function executeCommand(string $command): bool
    {
        $process = Process::fromShellCommandline(
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

        if (! $process->isSuccessful()) {
            warning("  Command failed: {$command}");
            $errorOutput = $process->getErrorOutput();
            if (! empty($errorOutput)) {
                warning('  Error: '.substr($errorOutput, 0, 500));
            }

            return false;
        }

        return true;
    }
}
