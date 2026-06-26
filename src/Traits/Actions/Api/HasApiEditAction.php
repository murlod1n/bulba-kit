<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method array<int, string> getRelationNames()
 * @method array<int, mixed> getSelectOptions()
 */
trait HasApiEditAction
{
    public function edit($id): JsonResponse
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::with($this->getRelationNames())->findOrFail($id);

        return response()->json([
            'item' => $item,
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
            'selectOptions' => $this->getSelectOptions(),
        ]);
    }
}
