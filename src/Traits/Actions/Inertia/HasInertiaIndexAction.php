<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Inertia\Inertia;
use Inertia\Response;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasInertiaIndexAction
{
    public function index(): Response
    {
        $definition = $this->getCrudDefinition();
        $items = $definition->model()::paginate(10);

        return Inertia::render($definition->pagePath().'/Index', [
            'items' => $items,
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
        ]);
    }
}
