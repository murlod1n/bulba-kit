<?php

namespace Nktlksvch\BulbaKit\Generators\Builders;

use Illuminate\Support\Str;

/**
 * FieldsBuilder
 *
 * Builds the fields array structure for Resource classes from field definitions
 * and relationship metadata. Produces an array of field descriptors used by
 * the admin frontend to render form inputs.
 *
 * Each field entry contains:
 * - name: database column name
 * - type: field type (string, text, integer, boolean, decimal, date, datetime, timestamp, json, image, gallery)
 * - label: human-readable label derived from the field name
 * - nullable (optional): whether the field accepts null values
 * - unique (optional): whether the field has a unique constraint
 * - collection (optional): media collection name (for image/gallery fields)
 * - max_files (optional): max number of files (for gallery fields)
 */
class FieldsBuilder
{
    /**
     * Build the fields array from field definitions and relationships.
     *
     * Includes user-defined fields and auto-generated FK fields for belongsTo
     * relationships (unless the FK field is already defined by the user).
     *
     * @param  array<int, array<string, mixed>>  $fields  Field definitions from askForFields()
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions from askForRelationships()
     * @param  array<int, string>  $translatableFields  Translatable field names
     * @return array<int, array<string, mixed>> Array of field descriptor arrays
     */
    public function build(array $fields, array $relationships, array $translatableFields = []): array
    {
        $result = [];
        $fieldNames = array_column($fields, 'name');

        foreach ($fields as $field) {
            // Image fields are managed by Media Library, not DB columns
            // Keep the original image field for form rendering
            if ($field['type'] === 'image') {
                $result[] = [
                    'name' => $field['name'],
                    'type' => 'image',
                    'label' => Str::title(str_replace('_', ' ', $field['name'])),
                    'collection' => $field['modifiers']['collection'] ?? $field['name'],
                ];

                continue;
            }

            // Gallery fields are managed by Media Library, not DB columns
            if ($field['type'] === 'gallery') {
                $result[] = [
                    'name' => $field['name'],
                    'type' => 'gallery',
                    'label' => Str::title(str_replace('_', ' ', $field['name'])),
                    'collection' => $field['modifiers']['collection'] ?? Str::snake(Str::plural($field['name'])),
                    'max_files' => $field['modifiers']['max_files'] ?? 0,
                ];

                continue;
            }

            $entry = [
                'name' => $field['name'],
                'type' => $field['type'],
                'label' => Str::title(str_replace('_', ' ', $field['name'])),
            ];

            if (isset($field['modifiers']['nullable'])) {
                $entry['nullable'] = true;
            }
            if (isset($field['modifiers']['unique'])) {
                $entry['unique'] = true;
            }
            if (in_array($field['name'], $translatableFields)) {
                $entry['translatable'] = true;
            }

            $result[] = $entry;
        }

        foreach ($relationships as $rel) {
            if ($rel['type'] !== 'belongsTo') {
                continue;
            }

            $fk = $rel['foreign_key'];
            if (in_array($fk, $fieldNames)) {
                continue;
            }

            $entry = [
                'name' => $fk,
                'type' => 'integer',
                'label' => Str::title(str_replace('_', ' ', $fk)),
            ];

            if ($rel['nullable'] ?? false) {
                $entry['nullable'] = true;
            }

            $result[] = $entry;
        }

        return $result;
    }
}
