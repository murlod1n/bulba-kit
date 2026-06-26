<?php

namespace Nktlksvch\BulbaKit\Traits;

use Illuminate\Database\Eloquent\Model;
use Nktlksvch\BulbaKit\Services\TranslationService;

/**
 * @method \Nktlksvch\BulbaKit\AbstractCrudDefinition getCrudDefinition()
 */
trait HasTranslationHelpers
{
    /**
     * Get translatable field names from the CRUD definition.
     *
     * @return array<int, string>
     */
    protected function getTranslatableFields(): array
    {
        return collect($this->getCrudDefinition()->fields())
            ->filter(fn ($f) => ($f['translatable'] ?? false))
            ->pluck('name')
            ->values()
            ->toArray();
    }

    /**
     * Check if the CRUD definition has any translatable fields.
     */
    protected function hasTranslatableFields(): bool
    {
        return count($this->getTranslatableFields()) > 0;
    }

    /**
     * Apply translatable field values from request to the model.
     *
     * Expects request data like: title => {en: "Hello", ru: "Привет"}
     * Uses spatie setTranslation() for each locale.
     *
     * @param  array<string, mixed>  $validated  Validated request data
     */
    protected function applyTranslatableFields(Model $item, array $validated): void
    {
        $translatableFields = $this->getTranslatableFields();

        foreach ($translatableFields as $field) {
            if (! isset($validated[$field]) || ! is_array($validated[$field])) {
                continue;
            }

            foreach ($validated[$field] as $locale => $value) {
                $item->setTranslation($field, $locale, (string) $value);
            }
        }
    }

    /**
     * Auto-translate missing locales for translatable fields using AI.
     *
     * Fills in empty locale values based on the default locale content.
     */
    protected function autoTranslateMissing(Model $item): void
    {
        $service = app(TranslationService::class);
        $translatableFields = $this->getTranslatableFields();
        $locales = config('bulba.locales', []);
        $defaultLocale = config('bulba.default_locale', 'en');

        foreach ($translatableFields as $field) {
            $translations = $item->getTranslations($field);
            $sourceText = $translations[$defaultLocale] ?? '';

            if (empty($sourceText)) {
                continue;
            }

            $missingLocales = array_values(
                array_filter($locales, fn ($l) => ! isset($translations[$l]) || empty($translations[$l]))
            );

            if (empty($missingLocales)) {
                continue;
            }

            $newTranslations = $service->translate($sourceText, $defaultLocale, $missingLocales);

            foreach ($newTranslations as $locale => $text) {
                if (! empty($text)) {
                    $item->setTranslation($field, $locale, $text);
                }
            }
        }

        $item->save();
    }

    /**
     * Prepare validated data for model creation with translatable fields.
     *
     * Translatable fields come as nested arrays {en: "...", ru: "..."}.
     * Non-translatable fields pass through unchanged.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepareTranslatableData(array $validated): array
    {
        $translatableFields = $this->getTranslatableFields();
        $result = [];

        foreach ($validated as $key => $value) {
            if (in_array($key, $translatableFields)) {
                // spatie expects the value to be set via setTranslation, not in fillable
                // But for create(), we can pass array and spatie handles it
                if (is_array($value)) {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
