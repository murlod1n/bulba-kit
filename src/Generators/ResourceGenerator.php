<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;
use Nktlksvch\BulbaKit\Generators\Builders\FieldsBuilder;
use Nktlksvch\BulbaKit\Generators\Builders\ValidationRulesBuilder;
use Nktlksvch\BulbaKit\Generators\Builders\RelationsBuilder;
use Nktlksvch\BulbaKit\Generators\Builders\ArrayRenderer;

/**
 * Resource Generator
 *
 * Generates Laravel Resource classes for admin CRUD operations.
 * Resources define fields, validation rules, and relationships metadata
 * used by the admin frontend to render forms and display data.
 *
 * @package Nktlksvch\BulbaKit\Generators
 */
class ResourceGenerator
{
    use LoadsStubs;

    public function __construct(
        private readonly FieldsBuilder $fieldsBuilder = new FieldsBuilder(),
        private readonly ValidationRulesBuilder $rulesBuilder = new ValidationRulesBuilder(),
        private readonly RelationsBuilder $relationsBuilder = new RelationsBuilder(),
        private readonly ArrayRenderer $renderer = new ArrayRenderer(),
    ) {}

    /**
     * Generate a new Resource class file.
     *
     * Creates a resource file with:
     * - Fields array (for form rendering)
     * - Validation rules (for form submission)
     * - Relations metadata (for select dropdowns and relation display)
     *
     * @param  string $name          Resource/model name (e.g., 'Post')
     * @param  array<int, array<string, mixed>>  $fields        Field definitions from askForFields()
     * @param  array<int, array<string, mixed>>  $relationships Relationship definitions from askForRelationships()
     * @return void
     */
    public function generate($name, $fields, $relationships): void
    {
        $resourceNamespace = config('bulba.resource_namespace', 'App\\Resources');
        $resourcePath = $this->resolveResourcePath($resourceNamespace);

        File::ensureDirectoryExists($resourcePath);

        $stub = $this->getStub('resource');

        $fieldsArray = $this->fieldsBuilder->build($fields, $relationships);
        $rulesArray = $this->rulesBuilder->build($fields, $name, $relationships);
        $relationsArray = $this->relationsBuilder->build($relationships);

        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{ model }}',
                '{{ fields }}',
                '{{ validationRules }}',
                '{{ relations }}',
            ],
            [
                $resourceNamespace,
                $name . 'Resource',
                $name,
                $this->renderer->render($fieldsArray),
                $this->renderer->render($rulesArray),
                empty($relationsArray)
                    ? '            // no relations'
                    : $this->renderer->render($relationsArray),
            ],
            $stub
        );

        File::put($resourcePath . '/' . $name . 'Resource.php', $content);
    }

    /**
     * Add an inverse relation to an existing resource file.
     *
     * When a new model defines a relationship with an existing model,
     * this method adds the inverse relationship metadata to the existing resource.
     *
     * For belongsTo relations, also adds the FK field to the fields array.
     *
     * @param  string $targetModel  Model to add the inverse relation to
     * @param  string $relationType Relation type (belongsTo, hasOne, hasMany, belongsToMany)
     * @param  string $currentModel Current model being created
     * @param  string $fk           Foreign key or pivot table name
     * @param  string $displayField Display field for select dropdowns
     * @return void
     */
    public function addInverseRelation(
        string $targetModel,
        string $relationType,
        string $currentModel,
        string $fk,
        string $displayField = 'name'
    ): void {
        $resourceNamespace = config('bulba.resource_namespace', 'App\\Resources');
        $resourcePath = $this->resolveResourcePath($resourceNamespace);
        $path = $resourcePath . '/' . $targetModel . 'Resource.php';

        if (!File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $relName = $this->resolveRelationName($relationType, $currentModel);

        // Skip if relation already exists
        if (Str::contains($content, "'{$relName}'")) {
            return;
        }

        $entry = $this->buildRelationEntry($relName, $relationType, $currentModel, $fk, $displayField);

        if (empty($entry)) {
            return;
        }

        // Insert relation entry into the relations array
        $content = $this->insertRelationEntry($content, $entry);
        File::put($path, $content);

        // For belongsTo relations, also add FK field to fields array
        if ($relationType === 'belongsTo') {
            $this->addFieldToResource($path, $fk);
        }
    }

    /**
     * Resolve the resource file path from namespace.
     *
     * @param  string $namespace Resource namespace
     * @return string Absolute path to resource directory
     */
    protected function resolveResourcePath(string $namespace): string
    {
        return str_replace('App\\', '', $namespace)
            |> (fn($x) => str_replace('\\', '/', $x))
            |> app_path(...);
    }

    /**
     * Resolve the relation method name based on type and model name.
     *
     * @param  string $relationType Relation type
     * @param  string $modelName   Related model name
     * @return string Relation name in camelCase
     */
    protected function resolveRelationName(string $relationType, string $modelName): string
    {
        return match ($relationType) {
            'hasMany' => Str::camel(Str::plural($modelName)),
            'hasOne' => Str::camel(Str::singular($modelName)),
            'belongsTo' => Str::camel(Str::singular($modelName)),
            'belongsToMany' => Str::camel(Str::plural($modelName)),
            default => Str::camel(Str::singular($modelName)),
        };
    }

    /**
     * Build a relation entry string for the resource file.
     *
     * @param  string $relName      Relation name
     * @param  string $relationType Relation type
     * @param  string $model        Related model name
     * @param  string $fk           Foreign key or pivot table
     * @param  string $displayField Display field
     * @return string PHP array entry string
     */
    protected function buildRelationEntry(
        string $relName,
        string $relationType,
        string $model,
        string $fk,
        string $displayField
    ): string {
        $entry = [
            'type' => $relationType,
            'model' => ArrayRenderer::EXPRESSION_PREFIX . '\\App\\Models\\' . $model . '::class',
            'display_field' => $displayField,
        ];

        if ($relationType === 'belongsTo' || $relationType === 'hasOne') {
            $entry['foreign_key'] = $fk;
        }

        if ($relationType === 'belongsToMany') {
            $entry['pivot_table'] = $fk;
        }

        return "            '{$relName}' => " . $this->renderer->renderValue($entry) . ',';
    }

    /**
     * Insert a relation entry into the resource file content.
     *
     * @param  string $content File content
     * @param  string $entry   Relation entry to insert
     * @return string Updated content
     */
    protected function insertRelationEntry(string $content, string $entry): string
    {
        if (Str::contains($content, '// no relations')) {
            return str_replace('// no relations', $entry, $content);
        }

        return preg_replace(
            '/(\];\s*$)/m',
            $entry . "\n        $1",
            $content,
            1
        );
    }

    /**
     * Add a FK field to the resource's fields() array.
     *
     * Uses regex to find the fields() method and insert a new field entry.
     *
     * @param  string $path Resource file path
     * @param  string $fk   Foreign key column name
     * @return void
     */
    protected function addFieldToResource(string $path, string $fk): void
    {
        $content = File::get($path);

        // Skip if field already exists
        if (Str::contains($content, "'name' => '{$fk}'")) {
            return;
        }

        $label = Str::title(str_replace('_', ' ', $fk));
        $fieldEntry = "            ['name' => '{$fk}', 'type' => 'integer', 'label' => '{$label}'],";

        $content = preg_replace(
            '/(public static function fields\(\): array\s*\{\s*return \[)(.*?)(\s*\];)/s',
            '$1$2' . "\n" . $fieldEntry . '$3',
            $content
        );

        File::put($path, $content);
    }
}
