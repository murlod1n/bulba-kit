<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Api;

use Illuminate\Http\JsonResponse;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method array<int, mixed> getSelectOptions()
 */
trait HasApiCreateAction
{
    public function create(): JsonResponse
    {
        $definition = $this->getCrudDefinition();

        return response()->json([
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
            'selectOptions' => $this->getSelectOptions(),
        ]);
    }
}
