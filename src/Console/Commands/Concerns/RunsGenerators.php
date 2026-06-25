<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use Nktlksvch\BulbaKit\Generators\AiConfigGenerator;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Generators\ReactPageGenerator;
use Nktlksvch\BulbaKit\Generators\ResourceGenerator;
use Nktlksvch\BulbaKit\Generators\RouteGenerator;

use function Laravel\Prompts\note;
use function Laravel\Prompts\progress;

/**
 * RunsGenerators Concern
 *
 * Handles the generation pipeline for admin resources.
 * Runs all generators in sequence and handles inverse relation generation.
 */
trait RunsGenerators
{
    /**
     * Run the complete generation pipeline.
     *
     * Generates: migration, model, resource, controller, AI config, React pages, routes.
     * Then handles inverse relations on existing models/resources.
     *
     * @param  string  $name  Resource name
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @param  array<int, array<string, mixed>>  $aiFields  AI generation field configs
     * @param  bool  $withTimestamps  Whether to include timestamps
     * @param  bool  $withSoftDeletes  Whether to include soft deletes
     * @param  string  $controllerType  Controller type (inertia/api)
     * @param  array<int, string>  $controllerMethods  Selected controller methods
     */
    protected function runGenerators(
        string $name,
        array $fields,
        array $relationships,
        array $aiFields,
        bool $withTimestamps,
        bool $withSoftDeletes,
        string $controllerType,
        array $controllerMethods
    ): void {
        $this->runMainGenerators(
            $name, $fields, $relationships, $aiFields,
            $withTimestamps, $withSoftDeletes, $controllerType, $controllerMethods
        );

        $this->runInverseGenerators($name, $relationships);
    }

    /**
     * Run the main generation steps with a progress bar.
     *
     * @param  string  $name  Resource name
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @param  array<int, array<string, mixed>>  $aiFields  AI generation field configs
     * @param  bool  $withTimestamps  Whether to include timestamps
     * @param  bool  $withSoftDeletes  Whether to include soft deletes
     * @param  string  $controllerType  Controller type (inertia/api)
     * @param  array<int, string>  $controllerMethods  Selected controller methods
     */
    protected function runMainGenerators(
        string $name,
        array $fields,
        array $relationships,
        array $aiFields,
        bool $withTimestamps,
        bool $withSoftDeletes,
        string $controllerType,
        array $controllerMethods
    ): void {
        $steps = [
            'Creating migration...',
            'Creating model...',
            'Creating resource class...',
            'Creating controller...',
            'Creating AI config...',
            'Creating React pages...',
            'Updating routes...',
        ];

        progress('Generating resource...', $steps, function ($step) use (
            $name, $fields, $relationships, $aiFields,
            $withTimestamps, $withSoftDeletes, $controllerType, $controllerMethods
        ) {
            match ($step) {
                'Creating migration...' => app(MigrationGenerator::class)->generate(
                    $name, $fields, $aiFields, $withTimestamps, $withSoftDeletes, $relationships
                ),
                'Creating model...' => app(ModelGenerator::class)->generate(
                    $name, $fields, $withSoftDeletes, $relationships
                ),
                'Creating resource class...' => app(ResourceGenerator::class)->generate(
                    $name, $fields, $relationships
                ),
                'Creating controller...' => app(ControllerGenerator::class)->generate(
                    $name, $controllerType, $controllerMethods, $fields
                ),
                'Creating AI config...' => app(AiConfigGenerator::class)->generate(
                    $name, $aiFields
                ),
                'Creating React pages...' => app(ReactPageGenerator::class)->generate(
                    $name, $fields, $relationships
                ),
                'Updating routes...' => app(RouteGenerator::class)->generate(
                    $name, $controllerType, $controllerMethods
                ),
            };
        });
    }

    /**
     * Run inverse relation generators on existing models/resources.
     *
     * For each relationship with an 'inverse' key:
     * 1. If FK is on target table, generate ALTER migration
     * 2. Add inverse relation method to target model
     * 3. Add inverse relation metadata to target resource
     *
     * @param  string  $name  Resource name (current model)
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     */
    protected function runInverseGenerators(string $name, array $relationships): void
    {
        foreach ($relationships as $rel) {
            if (! isset($rel['inverse'])) {
                continue;
            }

            $inverse = $rel['inverse'];
            $fkOrPivot = $inverse['pivot_table'] ?? $inverse['foreign_key'] ?? '';

            // Generate ALTER migration if FK is on target table
            if (($rel['fk_location'] ?? 'current') === 'target') {
                app(MigrationGenerator::class)->addForeignKeyToExisting(
                    $rel['target'],
                    $name,
                    $rel['foreign_key'],
                    $rel['cascade_on_delete'] ?? false
                );
            }

            // Add inverse relation to target model
            app(ModelGenerator::class)->addInverseRelation(
                $rel['target'],
                $inverse['type'],
                $name,
                $fkOrPivot
            );

            // Add inverse relation to target resource
            app(ResourceGenerator::class)->addInverseRelation(
                $rel['target'],
                $inverse['type'],
                $name,
                $fkOrPivot,
                $inverse['display_field'] ?? 'name'
            );
        }
    }

    /**
     * Display post-generation instructions.
     *
     * @param  string  $name  Resource name
     */
    protected function displayPostGenerationInstructions(string $name): void
    {
        note("Don't forget to:");
        note("  - Run 'php artisan migrate'");
        note('  - Add routes to your admin.php (or our auto-register did it)');
        note('  - Compile frontend assets: npm run build');
        note("  - Check AI config at config/admin/ai/{$name}.php and adjust as needed");
    }
}
