<?php

namespace Nktlksvch\BulbaKit\Trait;

use Inertia\Inertia;
use Inertia\Response;

/**
 * @method getCrudDefinition()
 */
trait HasIndexAction
{
    public function index(): Response
    {
        $definition = $this->getCrudDefinition();
        $items = $definition->model()::paginate(10);

        return Inertia::render($definition->route().'/Index', [
            'items' => $items,
            'fields' => $definition->fields(),
        ]);
    }
}
