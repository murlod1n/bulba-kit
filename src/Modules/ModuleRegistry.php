<?php

namespace Nktlksvch\BulbaKit\Modules;

use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;

class ModuleRegistry
{
    /**
     * @var array<string, ModuleInterface>
     */
    protected array $modules = [];

    public function register(ModuleInterface $module): self
    {
        $this->modules[$module->name()] = $module;

        return $this;
    }

    /**
     * @return array<string, ModuleInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->modules);
    }
}
