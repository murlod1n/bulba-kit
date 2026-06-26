<?php

namespace Nktlksvch\BulbaKit\Traits\Actions\Inertia;

use Inertia\Inertia;
use Inertia\Response;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 * @method array<int, mixed> getSelectOptions()
 */
trait HasInertiaCreateAction
{
    public function create(): Response
    {
        $definition = $this->getCrudDefinition();

        return Inertia::render($definition->pagePath().'/Create', [
            'fields' => $definition->fields(),
            'relations' => $definition->relations(),
            'selectOptions' => $this->getSelectOptions(),
            'locales' => config('bulba.locales', ['en']),
            'locale' => app()->getLocale(),
        ]);
    }
}
