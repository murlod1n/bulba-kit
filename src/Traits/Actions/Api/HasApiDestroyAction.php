<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasApiDestroyAction
{
    public function destroy($id): JsonResponse
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::findOrFail($id);
        $item->delete();

        return response()->json(null, 204);
    }
}
