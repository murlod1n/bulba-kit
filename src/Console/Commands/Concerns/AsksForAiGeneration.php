<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

/**
 * AsksForAiGeneration Concern
 *
 * Handles AI generation field configuration.
 * For text/json fields, allows enabling AI generation with prompt templates.
 */
trait AsksForAiGeneration
{
    /**
     * Interactively collect AI generation configurations.
     *
     * For each text/json field, asks if AI generation should be enabled.
     * If enabled, collects:
     * - Prompt template
     * - Context fields to use for generation
     *
     * @param  array<int, array<string, mixed>> $fields Field definitions
     * @return array<int, array<string, mixed>> AI field configurations with 'field', 'prompt', and 'context_fields' keys
     */
    protected function askForAiGeneration(array $fields): array
    {
        if (!config('bulba.ai_enabled', true)) {
            return [];
        }

        $aiFields = [];

        foreach ($fields as $field) {
            if (!$this->isAiCompatibleField($field)) {
                continue;
            }

            if (confirm("Enable AI generation for '{$field['name']}'?", default: false)) {
                $aiFields[] = $this->collectAiFieldConfig($field, $fields);
            }
        }

        return $aiFields;
    }

    /**
     * Check if a field type supports AI generation.
     *
     * @param  array<string, mixed> $field Field definition
     * @return bool
     */
    protected function isAiCompatibleField(array $field): bool
    {
        return in_array($field['type'], ['json', 'text']);
    }

    /**
     * Collect AI generation configuration for a single field.
     *
     * @param  array<string, mixed> $field  Field definition
     * @param  array<int, array<string, mixed>> $fields All field definitions (for context selection)
     * @return array<string, mixed> AI field configuration
     */
    protected function collectAiFieldConfig(array $field, array $fields): array
    {
        $prompt = text(
            label: "Enter prompt template for '{$field['name']}' (use {field} placeholders)",
            default: "Generate content for {$field['name']} based on title"
        );

        $contextFields = multiselect(
            label: 'Select context fields to use for generation',
            options: array_combine(array_column($fields, 'name'), array_column($fields, 'name')),
            default: ['title']
        );

        return [
            'field' => $field['name'],
            'prompt' => $prompt,
            'context_fields' => $contextFields,
        ];
    }
}
