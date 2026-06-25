<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Modules;

use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;
use Nktlksvch\BulbaKit\Modules\Redirects\RedirectsModule;
use PHPUnit\Framework\TestCase;

class RedirectsModuleTest extends TestCase
{
    protected RedirectsModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new RedirectsModule;
    }

    public function test_implements_module_interface(): void
    {
        $this->assertInstanceOf(ModuleInterface::class, $this->module);
    }

    public function test_name(): void
    {
        $this->assertSame('Redirects', $this->module->name());
    }

    public function test_description(): void
    {
        $this->assertNotEmpty($this->module->description());
    }

    public function test_icon(): void
    {
        $this->assertSame('arrow-right-left', $this->module->icon());
    }

    public function test_model_name(): void
    {
        $this->assertSame('Redirect', $this->module->modelName());
    }

    public function test_table_name(): void
    {
        $this->assertSame('redirects', $this->module->tableName());
    }

    public function test_fields_structure(): void
    {
        $fields = $this->module->fields();

        $this->assertNotEmpty($fields);

        foreach ($fields as $field) {
            $this->assertArrayHasKey('name', $field);
            $this->assertArrayHasKey('type', $field);
            $this->assertArrayHasKey('modifiers', $field);
        }
    }

    public function test_fields_contain_required_columns(): void
    {
        $fields = $this->module->fields();
        $names = array_column($fields, 'name');

        $this->assertContains('url_from', $names);
        $this->assertContains('url_to', $names);
        $this->assertContains('status_code', $names);
        $this->assertContains('is_active', $names);
    }

    public function test_url_from_is_unique(): void
    {
        $fields = $this->module->fields();
        $urlFromField = collect($fields)->firstWhere('name', 'url_from');

        $this->assertNotNull($urlFromField);
        $this->assertTrue($urlFromField['modifiers']['unique']);
    }

    public function test_status_code_defaults_to_301(): void
    {
        $fields = $this->module->fields();
        $statusCodeField = collect($fields)->firstWhere('name', 'status_code');

        $this->assertNotNull($statusCodeField);
        $this->assertSame(301, $statusCodeField['modifiers']['default']);
    }

    public function test_is_active_defaults_to_true(): void
    {
        $fields = $this->module->fields();
        $isActiveField = collect($fields)->firstWhere('name', 'is_active');

        $this->assertNotNull($isActiveField);
        $this->assertTrue($isActiveField['modifiers']['default']);
    }

    public function test_controller_methods(): void
    {
        $methods = $this->module->controllerMethods();

        $this->assertContains('index', $methods);
        $this->assertContains('create', $methods);
        $this->assertContains('store', $methods);
        $this->assertContains('edit', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('destroy', $methods);
        $this->assertNotContains('show', $methods);
    }

    public function test_options(): void
    {
        $options = $this->module->options();

        $this->assertTrue($options['timestamps']);
        $this->assertFalse($options['softDeletes']);
    }

    public function test_navigation(): void
    {
        $navigation = $this->module->navigation();

        $this->assertNotEmpty($navigation);
        $this->assertSame('Settings', $navigation[0]['group']);
        $this->assertCount(1, $navigation[0]['items']);
        $this->assertSame('Redirects', $navigation[0]['items'][0]['title']);
        $this->assertSame('/admin/redirects', $navigation[0]['items'][0]['href']);
        $this->assertSame('arrow-right-left', $navigation[0]['items'][0]['icon']);
    }

    public function test_no_seeder(): void
    {
        $this->assertNull($this->module->seederClass());
        $this->assertNull($this->module->seederStub());
    }

    public function test_custom_stubs(): void
    {
        $stubs = $this->module->customStubs();

        $this->assertArrayHasKey('index-page', $stubs);
        $this->assertArrayNotHasKey('controller-method', $stubs);
    }
}
