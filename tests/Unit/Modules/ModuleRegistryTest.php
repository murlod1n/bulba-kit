<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Modules;

use Nktlksvch\BulbaKit\Modules\ModuleRegistry;
use Nktlksvch\BulbaKit\Modules\Redirects\RedirectsModule;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    protected ModuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModuleRegistry;
    }

    public function test_register_and_get(): void
    {
        $module = new RedirectsModule;
        $this->registry->register($module);

        $this->assertSame($module, $this->registry->get('Redirects'));
        $this->assertNull($this->registry->get('NonExistent'));
    }

    public function test_all_returns_registered_modules(): void
    {
        $redirects = new RedirectsModule;

        $this->registry->register($redirects);

        $all = $this->registry->all();

        $this->assertCount(1, $all);
        $this->assertArrayHasKey('Redirects', $all);
    }

    public function test_names_returns_module_names(): void
    {
        $this->registry->register(new RedirectsModule);

        $names = $this->registry->names();

        $this->assertSame(['Redirects'], $names);
    }

    public function test_returns_empty_when_no_modules(): void
    {
        $this->assertEmpty($this->registry->all());
        $this->assertEmpty($this->registry->names());
        $this->assertNull($this->registry->get('anything'));
    }

    public function test_register_returns_self_for_chaining(): void
    {
        $result = $this->registry->register(new RedirectsModule);

        $this->assertSame($this->registry, $result);
    }

    public function test_overwrites_module_with_same_name(): void
    {
        $module1 = new RedirectsModule;
        $module2 = new RedirectsModule;

        $this->registry->register($module1);
        $this->registry->register($module2);

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($module2, $this->registry->get('Redirects'));
    }
}
