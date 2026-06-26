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

    public function test_navigation(): void
    {
        $navigation = $this->module->navigation();

        $this->assertNotEmpty($navigation);
        $this->assertSame('Settings', $navigation[0]['group']);
        $this->assertCount(1, $navigation[0]['items']);
        $this->assertSame('Website Settings', $navigation[0]['items'][0]['title']);
        $this->assertSame('/admin/website-settings', $navigation[0]['items'][0]['href']);
        $this->assertSame('settings', $navigation[0]['items'][0]['icon']);
    }

    public function test_has_install_method(): void
    {
        $this->assertTrue(method_exists($this->module, 'install'));
    }

    public function test_stubs_exist(): void
    {
        $stubsDir = dirname((new \ReflectionClass($this->module))->getFileName()).'/stubs';

        $expectedStubs = [
            'general-settings.stub',
            'seo-settings.stub',
            'general-settings-migration.stub',
            'seo-settings-migration.stub',
            'create_settings_table.stub',
            'website-settings-controller.stub',
            'website-settings-routes.stub',
            'website-settings-index-page.stub',
        ];

        foreach ($expectedStubs as $stub) {
            $this->assertFileExists(
                $stubsDir.'/'.$stub,
                "Stub {$stub} does not exist in {$stubsDir}"
            );
        }
    }
}
