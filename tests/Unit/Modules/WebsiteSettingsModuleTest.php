<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Modules;

use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;
use Nktlksvch\BulbaKit\Modules\WebsiteSettings\WebsiteSettingsModule;
use PHPUnit\Framework\TestCase;

class WebsiteSettingsModuleTest extends TestCase
{
    protected WebsiteSettingsModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new WebsiteSettingsModule;
    }

    public function test_implements_module_interface(): void
    {
        $this->assertInstanceOf(ModuleInterface::class, $this->module);
    }

    public function test_name(): void
    {
        $this->assertSame('Website Settings', $this->module->name());
    }

    public function test_description(): void
    {
        $this->assertNotEmpty($this->module->description());
    }

    public function test_icon(): void
    {
        $this->assertSame('settings', $this->module->icon());
    }

    public function test_model_name(): void
    {
        $this->assertSame('WebsiteSetting', $this->module->modelName());
    }

    public function test_table_name(): void
    {
        $this->assertSame('website_settings', $this->module->tableName());
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

        $this->assertContains('key', $names);
        $this->assertContains('value', $names);
        $this->assertContains('type', $names);
        $this->assertContains('group', $names);
        $this->assertContains('label', $names);
        $this->assertContains('sort', $names);
    }

    public function test_key_field_is_unique(): void
    {
        $fields = $this->module->fields();
        $keyField = collect($fields)->firstWhere('name', 'key');

        $this->assertNotNull($keyField);
        $this->assertTrue($keyField['modifiers']['unique']);
    }

    public function test_controller_methods(): void
    {
        $methods = $this->module->controllerMethods();

        $this->assertContains('index', $methods);
        $this->assertCount(1, $methods);
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
        $this->assertSame('Site Settings', $navigation[0]['items'][0]['title']);
        $this->assertSame('/admin/website-settings', $navigation[0]['items'][0]['href']);
        $this->assertSame('settings', $navigation[0]['items'][0]['icon']);
    }

    public function test_seeder_class(): void
    {
        $this->assertSame('WebsiteSettingSeeder', $this->module->seederClass());
    }

    public function test_seeder_stub(): void
    {
        $this->assertSame('website-setting-seeder', $this->module->seederStub());
    }

    public function test_custom_stubs(): void
    {
        $stubs = $this->module->customStubs();

        $this->assertArrayHasKey('index-page', $stubs);
        $this->assertArrayHasKey('controller-method', $stubs);
    }
}
