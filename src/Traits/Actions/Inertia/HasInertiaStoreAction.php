<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method void syncBelongsToMany($item, Request $request)
 * @method void runAiGeneration($item)
 * @method bool hasTranslatableFields()
 * @method void applyTranslatableFields($item, array<string, mixed> $validated)
 * @method void autoTranslateMissing($item)
 */
trait HasInertiaStoreAction
{
    public function store(Request $request): RedirectResponse
    {
        $definition = $this->getCrudDefinition();
        $validated = $request->validate($definition->validationRules());

        // Separate translatable fields from regular data
        if ($this->hasTranslatableFields()) {
            $translatableData = [];
            $regularData = [];

            $translatableNames = collect($definition->fields())
                ->filter(fn ($f) => ($f['translatable'] ?? false))
                ->pluck('name')
                ->toArray();

            foreach ($validated as $key => $value) {
                if (in_array($key, $translatableNames)) {
                    $translatableData[$key] = $value;
                } else {
                    $regularData[$key] = $value;
                }
            }

            $item = $definition->model()::create($regularData);
            $this->applyTranslatableFields($item, $translatableData);
            $item->save();
        } else {
            $item = $definition->model()::create($validated);
        }

        $this->syncBelongsToMany($item, $request);
        $this->runAiGeneration($item);

        // Auto-translate if requested
        if ($request->boolean('auto_translate') && $this->hasTranslatableFields()) {
            $this->autoTranslateMissing($item);
        }

        return redirect()->route($definition->routeName().'.index');
    }
}
