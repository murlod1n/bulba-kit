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

    public function test_has_install_method(): void
    {
        $this->assertTrue(method_exists($this->module, 'install'));
    }
}
