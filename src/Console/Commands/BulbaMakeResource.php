<?php

namespace Nktlksvch\BulbaKit\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Nktlksvch\BulbaKit\Console\Commands\Concerns\AsksForAiGeneration;
use Nktlksvch\BulbaKit\Console\Commands\Concerns\AsksForFields;
use Nktlksvch\BulbaKit\Console\Commands\Concerns\AsksForRelationships;
use Nktlksvch\BulbaKit\Console\Commands\Concerns\DisplaysSummary;
use Nktlksvch\BulbaKit\Console\Commands\Concerns\RunsGenerators;
use Nktlksvch\BulbaKit\Services\SchemaInspector;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Bulba Make Resource Command
 *
 * Interactive CLI command to generate a complete admin CRUD resource.
 * Generates: migration, model, resource, controller, React pages, routes, and AI config.
 *
 * Usage: php artisan bulba:make {name?}
 */
class BulbaMakeResource extends Command
{
    use AsksForFields;
    use AsksForRelationships;
    use AsksForAiGeneration;
    use DisplaysSummary;
    use RunsGenerators;

    protected $signature = 'bulba:make {name?}';
    protected $description = 'Generate a complete admin CRUD resource';

    /**
     * Schema inspector instance for database introspection.
     */
    protected SchemaInspector $schema;

    /**
     * Execute the command.
     *
     * Main flow:
     * 1. Collect resource name
     * 2. Collect field definitions
     * 3. Collect relationship definitions
     * 4. Collect AI generation configs
     * 5. Collect options (timestamps, soft deletes, controller type)
     * 6. Display summary and confirm
     * 7. Run all generators
     * 8. Display post-generation instructions
     *
     * @return void
     */
    public function handle(): void
    {
        $this->schema = new SchemaInspector();

        // Step 1: Collect resource name
        $name = $this->collectResourceName();

        // Step 2: Collect field definitions
        $fields = $this->collectFields();

        // Step 3: Collect relationship definitions
        $relationships = $this->askForRelationships($fields, $name, $this->schema);

        // Step 4: Collect AI generation configs
        $aiFields = $this->askForAiGeneration($fields);

        // Step 5: Collect options
        $options = $this->collectOptions();

        // Step 6: Display summary and confirm
        $confirmed = $this->displaySummary(
            $name,
            $fields,
            $relationships,
            $aiFields,
            $options['timestamps'],
            $options['softDeletes'],
            $options['controllerType'],
            $options['controllerMethods']
        );

        if (!$confirmed) {
            info('Cancelled.');
            return;
        }

        // Step 7: Run all generators
        $this->runGenerators(
            $name,
            $fields,
            $relationships,
            $aiFields,
            $options['timestamps'],
            $options['softDeletes'],
            $options['controllerType'],
            $options['controllerMethods']
        );

        // Step 8: Display post-generation instructions
        info("Resource {$name} generated successfully!");
        $this->displayPostGenerationInstructions($name);
    }

    /**
     * Collect the resource name from argument or prompt.
     *
     * @return string Resource name in PascalCase (e.g., 'Post', 'Comment')
     */
    protected function collectResourceName(): string
    {
        $name = $this->argument('name');

        if (!$name) {
            $name = text(
                label: 'What is the resource name? (Post, Comment, Car)',
                placeholder: 'Post',
                required: true,
                validate: fn($v) => !preg_match('/^[A-Z][a-zA-Z]+$/', $v)
                    ? 'Must be a valid class name like "Post"'
                    : null
            );
        }

        return $name;
    }

    /**
     * Collect field definitions with error handling.
     *
     * @return array<int, array<string, mixed>> Field definitions
     */
    protected function collectFields(): array
    {
        try {
            return $this->askForFields();
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return [];
        }
    }

    /**
     * Collect generation options (timestamps, soft deletes, controller config).
     *
     * @return array<string, mixed> Options with keys: 'timestamps', 'softDeletes', 'controllerType', 'controllerMethods'
     */
    protected function collectOptions(): array
    {
        $withTimestamps = confirm('Add timestamps (created_at, updated_at)?', default: true);
        $withSoftDeletes = confirm('Add Soft Delete?', default: false);

        $controllerType = select(
            label: 'Controller type?',
            options: [
                'inertia' => 'Inertia Controller (React)',
                'api' => 'API Controller (JSON)',
            ],
            default: 'inertia'
        );

        $controllerMethods = multiselect(
            label: 'Controller methods?',
            options: [
                'index' => 'index (list)',
                'create' => 'create (create form)',
                'store' => 'store (save)',
                'show' => 'show (view)',
                'edit' => 'edit (edit form)',
                'update' => 'update (update)',
                'destroy' => 'destroy (delete)',
            ],
            default: ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
        );

        return [
            'timestamps' => $withTimestamps,
            'softDeletes' => $withSoftDeletes,
            'controllerType' => $controllerType,
            'controllerMethods' => $controllerMethods,
        ];
    }
}
