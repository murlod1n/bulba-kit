<?php

namespace Nktlksvch\BulbaKit\Traits;

use Illuminate\Http\Request;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasCrudHelpers
{
    protected function getSelectOptions(): array
    {
        $definition = $this->getCrudDefinition();
        $options = [];

        foreach ($definition->relations() as $key => $rel) {
            if (($rel['type'] === 'belongsTo' || $rel['type'] === 'belongsToMany') && isset($rel['model'])) {
                $model = $rel['model'];
                $display = $rel['display_field'] ?? 'name';
                $options[$key] = $model::pluck($display, 'id')->toArray();
            }
        }

        return $options;
    }

    protected function getRelationNames(): array
    {
        return array_keys($this->getCrudDefinition()->relations());
    }

    protected function syncBelongsToMany($item, Request $request): void
    {
        foreach ($this->getCrudDefinition()->relations() as $key => $rel) {
            if ($rel['type'] === 'belongsToMany' && $request->has($key)) {
                $item->{$key}()->sync($request->input($key, []));
            }
        }
    }

    protected function runAiGeneration($item): void
    {
        $aiConfig = config('admin.ai.'.class_basename($item));

        if (! $aiConfig || ! $aiConfig['enabled']) {
            return;
        }
    }
}
