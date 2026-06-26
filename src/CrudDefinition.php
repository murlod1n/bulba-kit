<?php

namespace Nktlksvch\BulbaKit;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CrudDefinition
{
    public function __construct(
        public string $crudDefinitionClass
    ) {}
}
