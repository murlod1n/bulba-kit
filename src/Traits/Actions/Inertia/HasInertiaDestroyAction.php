<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Illuminate\Http\RedirectResponse;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasInertiaDestroyAction
{
    public function destroy($id): RedirectResponse
    {
        $definition = $this->getCrudDefinition();
        $item = $definition->model()::findOrFail($id);
        $item->delete();

        return redirect()->route($definition->routeName().'.index');
    }
}
