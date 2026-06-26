<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * GeneratesTranslations Concern
 *
 * Generates UI translation strings in lang/{locale}.json files
 * when a new CRUD resource is created.
 *
 * Handles static strings: navigation labels, field labels, page titles,
 * button text, and other UI elements.
 */
trait GeneratesTranslations
{
    /**
     * Generate UI translation strings for a CRUD resource.
     *
     * Adds translatable strings to all configured locale JSON files.
     * For the default locale, strings are used as-is (English keys).
     * For other locales, values are left empty (to be filled manually or by AI).
     *
     * @param  string  $name  Resource name (e.g., 'Post')
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     */
    protected function generateUiTranslations(string $name, array $fields): void
    {
        $locales = config('bulba.locales', []);
        $defaultLocale = config('bulba.default_locale', 'en');

        if (empty($locales)) {
            return;
        }

        $strings = $this->collectTranslatableStrings($name, $fields);

        foreach ($locales as $locale) {
            $this->appendToLangFile($locale, $strings, $locale === $defaultLocale);
        }
    }

    /**
     * Collect all UI strings that should be translatable for a resource.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    protected function collectTranslatableStrings(string $name, array $fields): array
    {
        $strings = [
            // Navigation
            $name,
            Str::plural($name),

            // Page titles
            "Create {$name}",
            "Edit {$name}",
            "{$name} Details",
            "{$name} List",

            // Common UI
            'Create',
            'Edit',
            'Delete',
            'Save',
            'Cancel',
            'Update',
            'View',
            'Back',
            'Actions',
            'ID',
            'Yes',
            'No',
            'Are you sure?',
            'This action cannot be undone.',
            'Save with auto-translation',
            'Creating...',
            'Updating...',
        ];

        // Field labels
        foreach ($fields as $field) {
            $strings[] = Str::title(str_replace('_', ' ', $field['name']));
        }

        return $strings;
    }

    /**
     * Append strings to a locale JSON lang file.
     *
     * For the default locale: key and value are the same string.
     * For other locales: key is set, value is empty (to be translated).
     *
     * @param  string  $locale  Locale code
     * @param  array<int, string>  $strings  Strings to add
     * @param  bool  $isDefault  Whether this is the default locale
     */
    protected function appendToLangFile(string $locale, array $strings, bool $isDefault): void
    {
        $path = lang_path("{$locale}.json");

        $existing = [];
        if (File::exists($path)) {
            $existing = json_decode(File::get($path), true) ?? [];
        }

        foreach ($strings as $str) {
            if (! isset($existing[$str])) {
                $existing[$str] = $isDefault ? $str : '';
            }
        }

        ksort($existing);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }
}
