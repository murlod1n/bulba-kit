<?php

namespace Nktlksvch\BulbaKit\Generators\Builders;

use Illuminate\Support\Str;

/**
 * ValidationRulesBuilder
 *
 * Builds Laravel validation rules array for Resource classes from field
 * definitions and relationship metadata. Rules are returned as arrays
 * (not pipe-separated strings) for clean code generation.
 *
 * Supported rule mappings:
 * - nullable/required: based on field modifiers
 * - max:N: for string fields with length modifier
 * - unique:table,column: for fields with unique modifier
 * - integer, boolean, numeric: type-based rules
 * - exists:table,id: for foreign key fields from belongsTo relationships
 */
class ValidationRulesBuilder
{
    /**
     * Build validation rules array from field definitions and relationships.
     *
     * @param  array<int, array<string, mixed>>  $fields  Field definitions from askForFields()
     * @param  string  $name  Resource/model name (used for unique table reference)
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions from askForRelationships()
     * @return array<string, array<int, string>> Associative array of field_name => rules_array
     */
    public function build(array $fields, string $name, array $relationships = []): array
    {
        $fkFields = [];

        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo') {
                $fkFields[$rel['foreign_key']] = [
                    'table' => Str::snake(Str::plural($rel['target'])),
                    'nullable' => $rel['nullable'] ?? false,
                ];
            }
        }

        $rules = [];

        foreach ($fields as $field) {
            // Skip image fields — they're handled by Media Library, not DB columns
            if ($field['type'] === 'image') {
                continue;
            }

            $rule = [];
            $rule[] = isset($field['modifiers']['nullable']) ? 'nullable' : 'required';

            if ($field['type'] === 'string' && isset($field['modifiers']['length'])) {
                $rule[] = 'max:'.$field['modifiers']['length'];
            }
            if (isset($field['modifiers']['unique'])) {
                $rule[] = 'unique:'.Str::plural(Str::lower($name)).','.$field['name'];
            }
            if ($field['type'] === 'integer') {
                $rule[] = 'integer';
            }
            if ($field['type'] === 'boolean') {
                $rule[] = 'boolean';
            }
            if ($field['type'] === 'decimal') {
                $rule[] = 'numeric';
            }

            $rules[$field['name']] = $rule;
        }

        foreach ($fkFields as $fk => $info) {
            if (in_array($fk, array_column($fields, 'name'))) {
                continue;
            }

            $rule = $info['nullable'] ? ['nullable'] : ['required'];
            $rule[] = 'integer';
            $rule[] = 'exists:'.$info['table'].',id';

            $rules[$fk] = $rule;
        }

        return $rules;
    }
}
