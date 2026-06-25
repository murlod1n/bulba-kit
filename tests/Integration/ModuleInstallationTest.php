<?php

namespace Nktlksvch\BulbaKit\Tests\Integration;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Generators\ResourceGenerator;
use Nktlksvch\BulbaKit\Generators\RouteGenerator;
use Nktlksvch\BulbaKit\Modules\Redirects\RedirectsModule;
use Nktlksvch\BulbaKit\Modules\WebsiteSettings\WebsiteSettingsModule;
use Nktlksvch\BulbaKit\Tests\TestCase;

class ModuleInstallationTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/bulba_module_test_'.uniqid();
        File::makeDirectory($this->tempDir, 0755, true);

        $this->app->useAppPath($this->tempDir.'/app');
        $this->app->useDatabasePath($this->tempDir.'/database');
        $this->app['config']->set('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $this->app['config']->set('bulba.resource_namespace', 'App\\Resources');
        $this->app['config']->set('bulba.react_pages_path', 'admin');
        $this->app['config']->set('bulba.auto_register_routes', false);

        // Ensure directories exist for generators
        File::ensureDirectoryExists($this->tempDir.'/database/migrations');
        File::ensureDirectoryExists($this->tempDir.'/app/Models');
        File::ensureDirectoryExists($this->tempDir.'/app/Resources');
        File::ensureDirectoryExists($this->tempDir.'/app/Http/Controllers/Admin');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }

    public function test_website_settings_module_generates_migration(): void
    {
        $module = new WebsiteSettingsModule;
        $fields = $module->fields();

        app(MigrationGenerator::class)->generate(
            $module->modelName(),
            $fields,
            [],
            $module->options()['timestamps'] ?? true,
            $module->options()['softDeletes'] ?? false,
            []
        );

        $migrationFiles = File::glob($this->tempDir.'/database/migrations/*_create_website_settings_table.php');
        $this->assertNotEmpty($migrationFiles);

        $content = File::get($migrationFiles[0]);
        $this->assertStringContainsString("Schema::create('website_settings'", $content);
        $this->assertStringContainsString("string('key'", $content);
        $this->assertStringContainsString("text('value'", $content);
        $this->assertStringContainsString("string('type'", $content);
        $this->assertStringContainsString("string('group'", $content);
        $this->assertStringContainsString("string('label'", $content);
        $this->assertStringContainsString("integer('sort'", $content);
        $this->assertStringContainsString('$table->timestamps()', $content);
    }

    public function test_website_settings_module_generates_model(): void
    {
        $module = new WebsiteSettingsModule;

        app(ModelGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            $module->options()['softDeletes'] ?? false,
            []
        );

        $modelPath = $this->tempDir.'/app/Models/WebsiteSetting.php';
        $this->assertFileExists($modelPath);

        $content = File::get($modelPath);
        $this->assertStringContainsString('class WebsiteSetting extends Model', $content);
        $this->assertStringContainsString("'key'", $content);
        $this->assertStringContainsString("'value'", $content);
        $this->assertStringContainsString("'type'", $content);
        $this->assertStringContainsString("'group'", $content);
        $this->assertStringContainsString("'label'", $content);
        $this->assertStringContainsString("'sort'", $content);
    }

    public function test_website_settings_module_generates_resource(): void
    {
        $module = new WebsiteSettingsModule;

        app(ResourceGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            []
        );

        $resourcePath = $this->tempDir.'/app/Resources/WebsiteSettingResource.php';
        $this->assertFileExists($resourcePath);

        $content = File::get($resourcePath);
        $this->assertStringContainsString('class WebsiteSettingResource extends AbstractResource', $content);
        $this->assertStringContainsString('WebsiteSetting::class', $content);
    }

    public function test_website_settings_module_generates_controller(): void
    {
        $module = new WebsiteSettingsModule;

        app(ControllerGenerator::class)->generate(
            $module->modelName(),
            'inertia',
            $module->controllerMethods()
        );

        $controllerPath = $this->tempDir.'/app/Http/Controllers/Admin/WebsiteSettingController.php';
        $this->assertFileExists($controllerPath);

        $content = File::get($controllerPath);
        $this->assertStringContainsString('class WebsiteSettingController extends Controller', $content);
        $this->assertStringContainsString('public function index(', $content);
    }

    public function test_redirects_module_generates_migration(): void
    {
        $module = new RedirectsModule;

        app(MigrationGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            [],
            $module->options()['timestamps'] ?? true,
            $module->options()['softDeletes'] ?? false,
            []
        );

        $migrationFiles = File::glob($this->tempDir.'/database/migrations/*_create_redirects_table.php');
        $this->assertNotEmpty($migrationFiles);

        $content = File::get($migrationFiles[0]);
        $this->assertStringContainsString("Schema::create('redirects'", $content);
        $this->assertStringContainsString("string('url_from'", $content);
        $this->assertStringContainsString("string('url_to'", $content);
        $this->assertStringContainsString("integer('status_code'", $content);
        $this->assertStringContainsString("boolean('is_active'", $content);
        $this->assertStringContainsString('->default(301)', $content);
        $this->assertStringContainsString('->default(true)', $content);
    }

    public function test_redirects_module_generates_model(): void
    {
        $module = new RedirectsModule;

        app(ModelGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            $module->options()['softDeletes'] ?? false,
            []
        );

        $modelPath = $this->tempDir.'/app/Models/Redirect.php';
        $this->assertFileExists($modelPath);

        $content = File::get($modelPath);
        $this->assertStringContainsString('class Redirect extends Model', $content);
        $this->assertStringContainsString("'url_from'", $content);
        $this->assertStringContainsString("'url_to'", $content);
        $this->assertStringContainsString("'status_code'", $content);
        $this->assertStringContainsString("'is_active'", $content);
    }

    public function test_module_fields_have_correct_default_modifiers(): void
    {
        $module = new RedirectsModule;
        $fields = $module->fields();

        $statusCodeField = collect($fields)->firstWhere('name', 'status_code');
        $this->assertSame(301, $statusCodeField['modifiers']['default']);

        $isActiveField = collect($fields)->firstWhere('name', 'is_active');
        $this->assertTrue($isActiveField['modifiers']['default']);
    }

    public function test_migration_generator_handles_default_modifiers(): void
    {
        $module = new RedirectsModule;

        app(MigrationGenerator::class)->generate(
            $module->modelName(),
            $module->fields(),
            [],
            true,
            false,
            []
        );

        $migrationFiles = File::glob($this->tempDir.'/database/migrations/*_create_redirects_table.php');
        $content = File::get($migrationFiles[0]);

        $this->assertStringContainsString('->default(301)', $content);
        $this->assertStringContainsString('->default(true)', $content);
    }
}
