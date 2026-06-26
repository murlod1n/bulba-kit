<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\DefaultCrud;

use Nktlksvch\BulbaKit\DefaultCrud\Contracts\DefaultCrud;
use Nktlksvch\BulbaKit\DefaultCrud\DefaultCrudRegistry;
use PHPUnit\Framework\TestCase;

class DefaultCrudRegistryTest extends TestCase
{
    protected DefaultCrudRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DefaultCrudRegistry;
    }

    protected function createResource(string $name): DefaultCrud
    {
        return new class($name) implements DefaultCrud
        {
            public function __construct(
                private readonly string $name,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function description(): string
            {
                return 'Test resource';
            }

            public function icon(): string
            {
                return 'test';
            }

            public function modelName(): string
            {
                return 'Test';
            }

            public function tableName(): string
            {
                return 'tests';
            }

            public function fields(): array
            {
                return [];
            }

            public function controllerMethods(): array
            {
                return ['index'];
            }

            public function options(): array
            {
                return ['timestamps' => true, 'softDeletes' => false];
            }

            public function navigation(): array
            {
                return [];
            }

            public function seederClass(): ?string
            {
                return null;
            }

            public function seederStub(): ?string
            {
                return null;
            }

            public function customStubs(): array
            {
                return [];
            }

            public function postInstall(object $command): void {}
        };
    }

    public function test_register_and_get(): void
    {
        $resource = $this->createResource('Test Resource');
        $this->registry->register($resource);

        $this->assertSame($resource, $this->registry->get('Test Resource'));
        $this->assertNull($this->registry->get('NonExistent'));
    }

    public function test_all_returns_registered_resources(): void
    {
        $resource = $this->createResource('Test Resource');
        $this->registry->register($resource);

        $all = $this->registry->all();

        $this->assertCount(1, $all);
        $this->assertArrayHasKey('Test Resource', $all);
    }

    public function test_names_returns_resource_names(): void
    {
        $this->registry->register($this->createResource('Test Resource'));

        $names = $this->registry->names();

        $this->assertSame(['Test Resource'], $names);
    }

    public function test_returns_empty_when_no_resources(): void
    {
        $this->assertEmpty($this->registry->all());
        $this->assertEmpty($this->registry->names());
        $this->assertNull($this->registry->get('anything'));
    }

    public function test_register_returns_self_for_chaining(): void
    {
        $result = $this->registry->register($this->createResource('Test Resource'));

        $this->assertSame($this->registry, $result);
    }

    public function test_overwrites_resource_with_same_name(): void
    {
        $resource1 = $this->createResource('Test Resource');
        $resource2 = $this->createResource('Test Resource');

        $this->registry->register($resource1);
        $this->registry->register($resource2);

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($resource2, $this->registry->get('Test Resource'));
    }
}
