<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use function Laravel\Prompts\multiselect;

/**
 * AsksForTranslatable Concern
 *
 * Handles interactive selection of translatable fields for the admin resource generator.
 * Only shown when multiple locales are configured.
 */
trait AsksForTranslatable
{
    /**
     * Ask the user which fields should be translatable.
     *
     * Only string and text fields are eligible. Translatable fields
     * are stored as JSON in the database (spatie/laravel-translatable).
     *
     * @param  array<int, array<string, mixed>>  $fields  Field definitions from askForFields()
     * @return array<int, string> Selected field names
     */
    protected function askForTranslatable(array $fields): array
    {
        $locales = config('bulba.locales', []);

        if (count($locales) <= 1) {
            return [];
        }

        $candidates = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['string', 'text'])) {
                $candidates[] = $field['name'];
            }
        }

        if (empty($candidates)) {
            return [];
        }

        $options = [];
        foreach ($candidates as $name) {
            $options[$name] = $name;
        }

        return multiselect(
            label: 'Which fields should be translatable? (stored as JSON, one value per language)',
            options: $options,
        );
    }
}
