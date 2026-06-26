<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method void syncBelongsToMany($item, Request $request)
 */
trait HasApiUpdateAction
{
    public function update(Request $request, $id): JsonResponse
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::findOrFail($id);
        $validated = $request->validate($definition->validationRules());
        $item->update($validated);

        $this->syncBelongsToMany($item, $request);

        return response()->json($item);
    }
}
