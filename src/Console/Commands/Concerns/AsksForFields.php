<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * AsksForFields Concern
 *
 * Handles interactive field collection for the admin resource generator.
 * Prompts user for field name, type, and modifiers (nullable, unique, length, etc.)
 */
trait AsksForFields
{
    /**
     * Interactively collect field definitions from the user.
     *
     * Loops until the user provides an empty field name.
     * For each field, asks for:
     * - Field name
     * - Field type (string, text, integer, boolean, decimal, date, datetime, timestamp, json)
     * - Type-specific modifiers (length for string, precision/scale for decimal)
     * - General modifiers (nullable, unique)
     *
     * @return array<int, array<string, mixed>> Array of field definitions, each with 'name', 'type', and 'modifiers' keys
     */
    protected function askForFields(): array
    {
        $fields = [];

        while (true) {
            $name = $this->askForFieldName();
            if (empty($name)) {
                break;
            }

            $type = $this->askForFieldType($name);
            $modifiers = $this->askForFieldModifiers($name, $type);

            $fields[] = [
                'name' => $name,
                'type' => $type,
                'modifiers' => $modifiers,
            ];
        }

        return $fields;
    }

    /**
     * Ask for a field name.
     *
     * @return string|null Field name or null if user wants to finish
     */
    protected function askForFieldName(): ?string
    {
        return text(
            label: 'Enter field name (or leave empty to finish)',
            required: false
        );
    }

    /**
     * Ask for the field type.
     *
     * Uses Laravel's Schema Builder which abstracts types across all supported
     * database drivers (MySQL, PostgreSQL, SQLite, SQL Server).
     *
     * @param  string  $name  Field name (for display)
     * @return string Field type
     */
    protected function askForFieldType(string $name): string
    {
        return select(
            label: "Type for '{$name}'",
            options: [
                'string' => 'string (VARCHAR)',
                'text' => 'text (TEXT)',
                'integer' => 'integer',
                'boolean' => 'boolean',
                'decimal' => 'decimal (precision, scale)',
                'date' => 'date',
                'datetime' => 'datetime',
                'timestamp' => 'timestamp',
                'json' => 'json',
                'image' => 'image (Media Library)',
            ],
            default: 'string'
        );
    }

    /**
     * Ask for field modifiers based on the field type.
     *
     * @param  string  $name  Field name
     * @param  string  $type  Field type
     * @return array<string, mixed> Modifiers array with keys like 'length', 'precision', 'scale', 'nullable', 'unique'
     */
    protected function askForFieldModifiers(string $name, string $type): array
    {
        $modifiers = [];

        // Type-specific modifiers
        if ($type === 'string') {
            $modifiers['length'] = text('Length (default 255)', default: '255');
        }

        if ($type === 'decimal') {
            $modifiers['precision'] = text('Precision (total digits)', default: '8');
            $modifiers['scale'] = text('Scale (decimal places)', default: '2');
        }

        if ($type === 'image') {
            $modifiers['collection'] = text('Media collection name', default: $name);
            $modifiers['thumb_width'] = (int) text('Thumbnail width', default: '200');
            $modifiers['thumb_height'] = (int) text('Thumbnail height', default: '200');
            $modifiers['single'] = true;
        }

        // General modifiers
        if (confirm("Make '{$name}' nullable?", default: false)) {
            $modifiers['nullable'] = true;
        }

        if (confirm("Make '{$name}' unique?", default: false)) {
            $modifiers['unique'] = true;
        }

        return $modifiers;
    }
}
