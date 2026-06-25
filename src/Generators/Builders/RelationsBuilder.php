<?php

namespace Nktlksvch\BulbaKit\Generators\Builders;

/**
 * RelationsBuilder
 *
 * Builds the relations metadata array for Resource classes from relationship
 * definitions. This metadata is used by the admin frontend to render relation
 * sections (select dropdowns, relation lists, pivot table management).
 *
 * Supported relation types:
 * - belongsTo: includes foreign_key for FK select dropdowns
 * - hasOne: includes foreign_key for reference
 * - hasMany: model and display_field only
 * - belongsToMany: includes pivot_table for pivot management
 */
class RelationsBuilder
{
    /**
     * Build relations metadata array from relationship definitions.
     *
     * @param  array<int, array<string, mixed>> $relationships Relationship definitions from askForRelationships()
     * @return array<string, array<string, mixed>> Associative array of relation_name => metadata_array
     */
    public function build(array $relationships): array
    {
        $result = [];

        foreach ($relationships as $rel) {
            $entry = [
                'type' => $rel['type'],
                'model' => ArrayRenderer::EXPRESSION_PREFIX . '\\App\\Models\\' . $rel['target'] . '::class',
                'display_field' => $rel['display_field'],
            ];

            if ($rel['type'] === 'belongsTo' || $rel['type'] === 'hasOne') {
                $entry['foreign_key'] = $rel['foreign_key'];
            }

            if ($rel['type'] === 'belongsToMany' && isset($rel['pivot_table'])) {
                $entry['pivot_table'] = $rel['pivot_table'];
            }

            $result[$rel['name']] = $entry;
        }

        return $result;
    }
}
