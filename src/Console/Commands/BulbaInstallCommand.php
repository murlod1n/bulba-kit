<?php

namespace Nktlksvch\BulbaKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Progress;
use Nktlksvch\BulbaKit\DefaultCrud\Contracts\DefaultCrud;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\CrudDefinitionGenerator;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Generators\ReactPageGenerator;
use Nktlksvch\BulbaKit\Generators\RouteGenerator;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;
use Nktlksvch\BulbaKit\Modules\ModuleRegistry;
use Nktlksvch\BulbaKit\Services\Install\BackendInstaller;
use Nktlksvch\BulbaKit\Services\Install\DefaultResourceInstaller;
use Nktlksvch\BulbaKit\Services\Install\InstallHelper;
use Nktlksvch\BulbaKit\Services\Install\JsInfrastructureInstaller;
use Nktlksvch\BulbaKit\Services\Install\NavigationGenerator;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class BulbaInstallCommand extends Command
{
    protected $signature = 'bulba:install {--force : Overwrite existing files}';

    protected $description = 'Install the complete BulbaKit React admin panel infrastructure';

    protected int $step = 0;

    protected int $totalSteps = 14;

    /** @var array<int, array{type: string, message: string}> */
    protected array $outputBuffer = [];

    /** @var ?Progress<int> */
    protected ?Progress $progress = null;

    public function handle(): void
    {
        $stubsPath = dirname(__DIR__, 2).'/Resources/install-stubs';

        $helper = new InstallHelper($stubsPath, $this->option('force'));
        $backendInstaller = new BackendInstaller($helper);
        $jsInstaller = new JsInfrastructureInstaller($helper);
        $defaultResourceInstaller = new DefaultResourceInstaller(
            app(MigrationGenerator::class),
            app(ModelGenerator::class),
            app(CrudDefinitionGenerator::class),
            app(ControllerGenerator::class),
            app(ReactPageGenerator::class),
            app(RouteGenerator::class),
        );
        $navigationGenerator = new NavigationGenerator;

        $this->displayBanner();

        $this->progress = progress(label: 'Installing BulbaKit...', steps: $this->totalSteps);
        $this->progress->start();

        $this->step('Installing PHP dependencies', 'composer require inertia wayfinder fortify medialibrary');
        $backendInstaller->installPhpDependencies();

        $this->step('Installing NPM dependencies', 'react, tailwind, lucide, sonner, and more');
        $jsInstaller->installNpmDependencies();

        $this->step('Creating configuration files', 'tsconfig, vite, eslint, prettier, phpstan');
        $jsInstaller->createConfigFiles();

        $this->step('Creating CSS with Tailwind v4', 'Setting up app.css');
        $jsInstaller->createCss();

        $this->step('Initializing shadcn/ui', 'Setting up component library');
        $failed = $jsInstaller->initShadcn();
        if (! empty($failed)) {
            $this->bufferWarning('  Failed to install components: '.implode(', ', $failed));
        }

        $this->step('Creating Blade template', 'app.blade.php with Inertia');
        $backendInstaller->createBladeTemplate();

        $this->step('Creating JS infrastructure', 'app.tsx, hooks, types, utilities');
        $jsInstaller->createJsInfrastructure();

        $this->step('Creating React components', 'Layouts, nav, auth, UI components');
        $jsInstaller->createComponents();

        $this->step('Creating pages', 'Dashboard, auth, settings');
        $jsInstaller->createPages();

        $this->step('Creating backend infrastructure', 'Providers, controllers, middleware, routes');
        $backendInstaller->createBackend();

        // Default resources (automatic)
        $this->step('Installing default resources', 'Setting up built-in CRUD resources');
        $features = $defaultResourceInstaller->install($this);

        // Modules (user selection)
        $this->flushBuffer();
        $modules = $this->selectModules();
        $this->installModules($modules);

        // Locale setup
        $this->flushBuffer();
        $locales = $this->selectLocales();
        $this->setupLocales($locales, $backendInstaller);

        // Generate navigation from both registries
        $navigationGenerator->generate($features, $modules);

        // Record installed items in config
        $this->recordInstalled($features, $modules);

        // Wayfinder must run after all routes are registered
        $this->step('Generating Wayfinder routes', 'php artisan wayfinder:generate');
        $backendInstaller->runWayfinder();

        $backendInstaller->cleanupOldFiles();

        $this->flushBuffer();
        $this->progress->finish();

        $this->newLine();
        info('BulbaKit installed successfully!');
        $this->newLine();
        note('Next steps:');
        note('  1. npm run build (or npm run dev)');
        note('  2. php artisan migrate');
        note('  3. Configure .env (APP_NAME, DB, MAIL, etc.)');
        note('  4. php artisan bulba:make to create CRUD resources');
    }

    // --- Modules (user selection) ---

    /**
     * Prompt the user to select which modules to install.
     *
     * @return array<int, ModuleInterface>
     */
    protected function selectModules(): array
    {
        $this->step('Selecting modules', 'Choose optional modules to install');

        $registry = app(ModuleRegistry::class);

        if (empty($registry->all())) {
            return [];
        }

        $options = [];
        foreach ($registry->all() as $name => $module) {
            $options[$name] = "{$module->description()}";
        }

        $selected = multiselect(
            label: 'Which modules would you like to install?',
            options: $options,
        );

        return array_map(fn ($name) => $registry->get($name), array_filter($selected));
    }

    /**
     * Install selected modules by delegating to each module's install() method.
     *
     * @param  array<int, ModuleInterface>  $modules
     */
    protected function installModules(array $modules): void
    {
        if (empty($modules)) {
            return;
        }

        $this->step('Installing modules', 'Setting up selected modules');

        foreach ($modules as $module) {
            $this->info("  Installing: {$module->name()}");

            $module->install($this);
        }
    }

    // --- Config recording ---

    /**
     * Record installed features and modules in config/bulba.php.
     *
     * @param  array<int, DefaultCrud>  $features
     * @param  array<int, ModuleInterface>  $modules
     */
    protected function recordInstalled(array $features, array $modules): void
    {
        $configPath = config_path('bulba.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);

        $featureNames = array_map(fn ($f) => "'{$f->name()}'", $features);
        $moduleNames = array_map(fn ($m) => "'{$m->name()}'", $modules);

        $featuresList = implode(', ', $featureNames);
        $modulesList = implode(', ', $moduleNames);

        $content = preg_replace(
            "/'features' => \[.*?\]/s",
            "'features' => [{$featuresList}]",
            $content
        );

        $content = preg_replace(
            "/'modules' => \[.*?\]/s",
            "'modules' => [{$modulesList}]",
            $content
        );

        File::put($configPath, $content);
    }

    // --- Locale setup ---

    /**
     * Prompt the user to select which locales to support.
     *
     * @return array<int, string>
     */
    protected function selectLocales(): array
    {
        $this->step('Configuring locales', 'Choose supported languages');

        $locales = multiselect(
            label: 'Which languages should the app support?',
            options: [
                'en' => 'English',
                'ru' => 'Russian',
            ],
            default: ['en', 'ru'],
        );

        if (empty($locales)) {
            $locales = ['en'];
        }

        return $locales;
    }

    /**
     * Set up locale configuration: update config, generate lang files, install middleware.
     *
     * @param  array<int, string>  $locales
     */
    protected function setupLocales(array $locales, BackendInstaller $backendInstaller): void
    {
        $this->recordLocales($locales);

        $this->generateLangFiles($locales);

        $backendInstaller->installLocaleMiddleware();
    }

    /**
     * Record locales in config/bulba.php.
     *
     * @param  array<int, string>  $locales
     */
    protected function recordLocales(array $locales): void
    {
        $configPath = config_path('bulba.php');

        if (! File::exists($configPath)) {
            return;
        }

        $content = File::get($configPath);

        $localesList = implode(', ', array_map(fn ($l) => "'{$l}'", $locales));

        $content = preg_replace(
            "/'locales' => \[.*?\]/s",
            "'locales' => [{$localesList}]",
            $content
        );

        File::put($configPath, $content);
    }

    /**
     * Generate empty JSON lang files for each locale.
     *
     * @param  array<int, string>  $locales
     */
    protected function generateLangFiles(array $locales): void
    {
        foreach ($locales as $locale) {
            $path = lang_path("{$locale}.json");

            if (File::exists($path)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, "{}\n");
        }
    }

    // --- UI helpers ---

    protected function step(string $label, string $hint = ''): void
    {
        $this->flushBuffer();
        if ($this->progress) {
            $this->progress->label($label);
            if ($hint) {
                $this->progress->hint($hint);
            }
            $this->progress->advance();
        }
        $this->step++;
    }

    protected function displayBanner(): void
    {
        $b = '<fg=cyan>';
        $e = '</>';

        $this->newLine();
        $this->line("{$b}   ██████╗  ██╗   ██╗ ██╗      ██████╗   █████╗ {$e}");
        $this->line("{$b}   ██╔══██╗ ██║   ██║ ██║      ██╔══██╗ ██╔══██╗{$e}");
        $this->line("{$b}   ██████╔╝ ██║   ██║ ██║      ██████╔╝ ███████║{$e}");
        $this->line("{$b}   ██╔══██╗ ██║   ██║ ██║      ██╔══██╗ ██╔══██║{$e}");
        $this->line("{$b}   ██████╔╝ ╚██████╔╝ ███████╗ ██████╔╝ ██║  ██║{$e}");
        $this->line("{$b}   ╚═════╝   ╚═════╝  ╚══════╝ ╚═════╝  ╚═╝  ╚═╝{$e}");
        $this->newLine();
    }

    protected function bufferWarning(string $message): void
    {
        $this->outputBuffer[] = ['type' => 'warning', 'message' => $message];
    }

    protected function flushBuffer(): void
    {
        if (empty($this->outputBuffer)) {
            return;
        }

        foreach ($this->outputBuffer as $item) {
            if ($item['type'] === 'warning') {
                warning($item['message']);
            } else {
                note($item['message']);
            }
        }

        $this->outputBuffer = [];
    }
}
