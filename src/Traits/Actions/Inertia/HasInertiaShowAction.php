<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Inertia\Inertia;
use Inertia\Response;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method array<int, string> getRelationNames()
 */
trait HasInertiaShowAction
{
    public function show($id): Response
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::with($this->getRelationNames())->findOrFail($id);

        return Inertia::render($definition->pagePath().'/Show', [
            'item' => $item,
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
        ]);
    }
}
