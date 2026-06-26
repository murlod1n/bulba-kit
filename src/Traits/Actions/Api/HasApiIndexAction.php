<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasApiIndexAction
{
    public function index(): JsonResponse
    {
        $definition = $this->getCrudDefinition();
        $items = $definition->model()::paginate(10);

        return response()->json([
            'items' => $items,
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
        ]);
    }
}
