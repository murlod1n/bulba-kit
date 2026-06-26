<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method array<int, string> getRelationNames()
 */
trait HasApiShowAction
{
    public function show($id): JsonResponse
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::with($this->getRelationNames())->findOrFail($id);

        return response()->json([
            'item' => $item,
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
        ]);
    }
}
