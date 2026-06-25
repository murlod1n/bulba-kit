<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

/**
 * DisplaysSummary Concern
 *
 * Handles the summary display before generation starts.
 * Shows all collected data (fields, relationships, AI config) and asks for confirmation.
 */
trait DisplaysSummary
{
    /**
     * Display a summary of all collected data and ask for generation confirmation.
     *
     * @param  string $name            Resource name
     * @param  array  $fields          Field definitions
     * @param  array  $relationships   Relationship definitions
     * @param  array  $aiFields        AI generation field configs
     * @param  bool   $withTimestamps  Whether to include timestamps
     * @param  bool   $withSoftDeletes Whether to include soft deletes
     * @param  string $controllerType  Controller type (inertia/api)
     * @param  array  $controllerMethods Selected controller methods
     * @return bool   True if user confirms generation, false otherwise
     */
    protected function displaySummary(
        string $name,
        array $fields,
        array $relationships,
        array $aiFields,
        bool $withTimestamps,
        bool $withSoftDeletes,
        string $controllerType,
        array $controllerMethods
    ): bool {
        info('Summary:');

        note("Resource: {$name}");

        $this->displayFieldsSummary($fields);
        $this->displayOptionsSummary($withTimestamps, $withSoftDeletes);
        $this->displayRelationshipsSummary($relationships);
        $this->displayAiFieldsSummary($aiFields);
        $this->displayControllerSummary($controllerType, $controllerMethods);

        return confirm('Proceed with generation?');
    }

    /**
     * Display fields summary.
     *
     * @param array $fields Field definitions
     */
    protected function displayFieldsSummary(array $fields): void
    {
        note("Fields:");
        foreach ($fields as $field) {
            $mods = !empty($field['modifiers'])
                ? ' (' . implode(', ', array_keys($field['modifiers'])) . ')'
                : '';
            note("  - {$field['name']}: {$field['type']}{$mods}");
        }
    }

    /**
     * Display options summary (timestamps, soft deletes).
     *
     * @param bool $withTimestamps  Whether timestamps are enabled
     * @param bool $withSoftDeletes Whether soft deletes are enabled
     */
    protected function displayOptionsSummary(bool $withTimestamps, bool $withSoftDeletes): void
    {
        if ($withTimestamps) {
            note('  + timestamps');
        }
        if ($withSoftDeletes) {
            note('  + soft deletes');
        }
    }

    /**
     * Display relationships summary.
     *
     * @param array $relationships Relationship definitions
     */
    protected function displayRelationshipsSummary(array $relationships): void
    {
        if (empty($relationships)) {
            return;
        }

        note("Relationships:");
        foreach ($relationships as $rel) {
            $extra = '';

            if ($rel['type'] === 'belongsTo' || $rel['type'] === 'hasOne' || $rel['type'] === 'hasMany') {
                $extra = " (FK: {$rel['foreign_key']})";
            }
            if ($rel['type'] === 'belongsToMany') {
                $extra = " (pivot: {$rel['pivot_table']})";
            }

            note("  - {$rel['name']}: {$rel['type']} to {$rel['target']}{$extra}");
        }
    }

    /**
     * Display AI generation fields summary.
     *
     * @param array $aiFields AI field configurations
     */
    protected function displayAiFieldsSummary(array $aiFields): void
    {
        if (empty($aiFields)) {
            return;
        }

        note("AI generated fields:");
        foreach ($aiFields as $ai) {
            note("  - {$ai['field']} (prompt: {$ai['prompt']})");
        }
    }

    /**
     * Display controller configuration summary.
     *
     * @param string $controllerType    Controller type (inertia/api)
     * @param array  $controllerMethods Selected controller methods
     */
    protected function displayControllerSummary(string $controllerType, array $controllerMethods): void
    {
        note("Controller: {$controllerType}");
        note("Methods: " . implode(', ', $controllerMethods));
    }
}
