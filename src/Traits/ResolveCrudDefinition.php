<?php

namespace Nktlksvch\BulbaKit\Traits;

use Nktlksvch\BulbaKit\AbstractCrudDefinition;
use Nktlksvch\BulbaKit\CrudDefinition;
use ReflectionClass;
use RuntimeException;

trait ResolveCrudDefinition
{
    private ?AbstractCrudDefinition $crudDefinitionInstance = null;

    protected function getCrudDefinition(): AbstractCrudDefinition
    {
        if ($this->crudDefinitionInstance !== null) {
            return $this->crudDefinitionInstance;
        }

        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(CrudDefinition::class);

        if (empty($attributes)) {
            throw new RuntimeException(
                sprintf('Controller %s must have #[CrudDefinition] attribute.', get_class($this))
            );
        }

        $crudDefinitionClass = $attributes[0]->newInstance()->crudDefinitionClass;

        if (! class_exists($crudDefinitionClass)) {
            throw new RuntimeException(
                sprintf('Schema class "%s" targeted by attribute does not exist.', $crudDefinitionClass)
            );
        }

        return $this->crudDefinitionInstance = app($crudDefinitionClass);
    }
}
