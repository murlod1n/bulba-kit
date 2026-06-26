<?php

namespace Nktlksvch\BulbaKit\DefaultCrud;

use Nktlksvch\BulbaKit\Console\Commands\BulbaInstallCommand;
use Nktlksvch\BulbaKit\DefaultCrud\Contracts\DefaultCrud;
use Nktlksvch\BulbaKit\Providers\BulbaKitServiceProvider;

/**
 * Registry for default CRUD resources.
 *
 * Collects all registered DefaultCrud implementations and provides lookup
 * by name. Registered as a singleton in BulbaKitServiceProvider.
 * During `bulba:install`, all resources in this registry are installed automatically.
 *
 * @see BulbaKitServiceProvider
 * @see BulbaInstallCommand::installDefaultResources()
 */
class DefaultCrudRegistry
{
    /**
     * @var array<string, DefaultCrud>
     */
    protected array $resources = [];

    /**
     * Register a default CRUD resource.
     */
    public function register(DefaultCrud $resource): self
    {
        $this->resources[$resource->name()] = $resource;

        return $this;
    }

    /**
     * Get all registered default CRUD resources.
     *
     * @return array<string, DefaultCrud>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * Get a resource by name, or null if not found.
     */
    public function get(string $name): ?DefaultCrud
    {
        return $this->resources[$name] ?? null;
    }

    /**
     * Get all registered resource names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->resources);
    }
}
