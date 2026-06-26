<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method void syncBelongsToMany($item, Request $request)
 * @method void runAiGeneration($item)
 */
trait HasApiStoreAction
{
    public function store(Request $request): JsonResponse
    {
        $definition = $this->getCrudDefinition();
        $validated = $request->validate($definition->validationRules());
        $item = $definition->model()::create($validated);

        $this->syncBelongsToMany($item, $request);
        $this->runAiGeneration($item);

        return response()->json($item, 201);
    }
}
