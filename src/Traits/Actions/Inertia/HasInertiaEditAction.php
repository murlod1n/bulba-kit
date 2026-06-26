<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Inertia\Inertia;
use Inertia\Response;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method array<int, string> getRelationNames()
 * @method array<int, mixed> getSelectOptions()
 */
trait HasInertiaEditAction
{
    public function edit($id): Response
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::with($this->getRelationNames())->findOrFail($id);

        return Inertia::render($definition->pagePath().'/Edit', [
            'item' => $item,
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
            'selectOptions' => $this->getSelectOptions(),
            'locales' => config('bulba.locales', ['en']),
            'locale' => app()->getLocale(),
        ]);
    }
}
