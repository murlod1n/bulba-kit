<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method void syncBelongsToMany($item, Request $request)
 * @method bool hasTranslatableFields()
 * @method void applyTranslatableFields($item, array<string, mixed> $validated)
 * @method void autoTranslateMissing($item)
 */
trait HasInertiaUpdateAction
{
    public function update(Request $request, $id): RedirectResponse
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::findOrFail($id);
        $validated = $request->validate($definition->validationRules());

        // Separate translatable fields from regular data
        if ($this->hasTranslatableFields()) {
            $translatableNames = collect($definition->fields())
                ->filter(fn ($f) => ($f['translatable'] ?? false))
                ->pluck('name')
                ->toArray();

            $translatableData = [];
            $regularData = [];

            foreach ($validated as $key => $value) {
                if (in_array($key, $translatableNames)) {
                    $translatableData[$key] = $value;
                } else {
                    $regularData[$key] = $value;
                }
            }

            $item->update($regularData);
            $this->applyTranslatableFields($item, $translatableData);
            $item->save();
        } else {
            $item->update($validated);
        }

        $this->syncBelongsToMany($item, $request);

        // Auto-translate if requested
        if ($request->boolean('auto_translate') && $this->hasTranslatableFields()) {
            $this->autoTranslateMissing($item);
        }

        return redirect()->route($definition->routeName().'.index');
    }
}
