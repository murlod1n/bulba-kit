<?php

namespace Nktlksvch\BulbaKit\Console\Commands\Concerns;

use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\Services\SchemaInspector;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * AsksForRelationships Concern
 *
 * Handles interactive relationship collection for the admin resource generator.
 * Supports three relationship types:
 * - FK on current table (belongsTo)
 * - FK on target table (hasOne/hasMany)
 * - Pivot table (belongsToMany)
 *
 * Each relationship automatically generates inverse relations on the target model.
 */
trait AsksForRelationships
{
    /**
     * Interactively collect relationship definitions from the user.
     *
     * @param  array  $fields        Current field definitions (passed by reference for FK additions)
     * @param  string $currentModel  Name of the model being created
     * @param  SchemaInspector $schema Schema inspector instance
     * @return array  Relationship definitions with inverse metadata
     */
    protected function askForRelationships(array &$fields, string $currentModel, SchemaInspector $schema): array
    {
        $relationships = [];

        if (!confirm('Add relationships to other tables?', default: false)) {
            return $relationships;
        }

        $existingTables = $schema->getExistingTables();
        $currentTable = Str::snake(Str::plural($currentModel));

        while (true) {
            $fkLocation = $this->askForFkLocation($currentTable);

            if ($fkLocation === 'pivot') {
                $relationships[] = $this->askForPivotRelationship($existingTables, $currentTable, $currentModel, $schema);
            } else {
                $relationships[] = $this->askForStandardRelationship($existingTables, $currentTable, $currentModel, $fkLocation, $schema);
            }

            if (!confirm('Add another relationship?', default: false)) {
                break;
            }
        }

        return $relationships;
    }

    /**
     * Ask where the foreign key should be placed.
     *
     * @param  string $currentTable Current table name
     * @return string 'current', 'target', or 'pivot'
     */
    protected function askForFkLocation(string $currentTable): string
    {
        return select(
            label: 'Where should the foreign key be placed?',
            options: [
                'current' => "On current table ({$currentTable})",
                'target'  => 'On related table',
                'pivot'   => 'Pivot table (many-to-many)',
            ]
        );
    }

    /**
     * Build relationship data for a standard (non-pivot) relationship.
     *
     * @param  array  $existingTables Available tables
     * @param  string $currentTable   Current table name
     * @param  string $currentModel   Current model name
     * @param  string $fkLocation     'current' or 'target'
     * @param  SchemaInspector $schema Schema inspector instance
     * @return array  Relationship definition with inverse metadata
     */
    protected function askForStandardRelationship(
        array $existingTables,
        string $currentTable,
        string $currentModel,
        string $fkLocation,
        SchemaInspector $schema
    ): array {
        $relType = $this->askForRelationType();
        $targetTable = $this->askForTable($existingTables, 'Target table');
        $targetModel = Str::studly(Str::singular($targetTable));
        $columns = $schema->getTableColumns($targetTable);
        $displayField = $this->askForColumn($columns, 'Display field', 'name');

        $nullable = confirm('FK nullable?', default: false);
        $cascadeOnDelete = confirm('Cascade on delete?', default: false);

        // Determine relation types and names based on FK location
        if ($fkLocation === 'current') {
            $fk = Str::snake($targetModel) . '_id';
            $currentRelType = 'belongsTo';
            $inverseType = ($relType === 'one') ? 'hasOne' : 'hasMany';
            $relName = Str::camel($targetModel);
            $inverseName = ($relType === 'one')
                ? Str::camel(Str::singular($currentModel))
                : Str::camel(Str::plural($currentModel));
        } else {
            $fk = Str::snake($currentModel) . '_id';
            $currentRelType = ($relType === 'one') ? 'hasOne' : 'hasMany';
            $inverseType = 'belongsTo';
            $relName = ($relType === 'one')
                ? Str::camel(Str::singular($targetModel))
                : Str::camel(Str::plural($targetModel));
            $inverseName = Str::camel(Str::singular($currentModel));
        }

        // Detect display field for inverse relation
        $inverseDisplayField = $schema->detectDisplayField($currentTable);

        return [
            'name' => $relName,
            'type' => $currentRelType,
            'target' => $targetModel,
            'foreign_key' => $fk,
            'display_field' => $displayField,
            'nullable' => $nullable,
            'cascade_on_delete' => $cascadeOnDelete,
            'fk_location' => $fkLocation,
            'inverse' => [
                'name' => $inverseName,
                'type' => $inverseType,
                'model' => $currentModel,
                'foreign_key' => $fk,
                'display_field' => $inverseDisplayField,
            ],
        ];
    }

    /**
     * Build relationship data for a pivot (many-to-many) relationship.
     *
     * @param  array  $existingTables Available tables
     * @param  string $currentTable   Current table name
     * @param  string $currentModel   Current model name
     * @param  SchemaInspector $schema Schema inspector instance
     * @return array  Relationship definition with inverse metadata
     */
    protected function askForPivotRelationship(
        array $existingTables,
        string $currentTable,
        string $currentModel,
        SchemaInspector $schema
    ): array {
        $targetTable = $this->askForTable($existingTables, 'Second table');
        $targetModel = Str::studly(Str::singular($targetTable));
        $columns = $schema->getTableColumns($targetTable);
        $displayField = $this->askForColumn($columns, 'Display field', 'name');

        $pivotTable = $this->askForPivotTable($existingTables, $targetTable, $currentModel);

        // Detect display field for inverse relation
        $inverseDisplayField = $schema->detectDisplayField($currentTable);

        return [
            'name' => Str::camel(Str::plural($targetModel)),
            'type' => 'belongsToMany',
            'target' => $targetModel,
            'display_field' => $displayField,
            'pivot_table' => $pivotTable,
            'pivot_tables' => [
                Str::snake(Str::plural($currentModel)),
                Str::snake(Str::plural($targetModel)),
            ],
        ];
    }

    /**
     * Ask for the relationship cardinality (one-to-one or one-to-many).
     *
     * @return string 'one' or 'many'
     */
    protected function askForRelationType(): string
    {
        return select(
            label: 'Relationship type?',
            options: [
                'one'  => 'One to one (hasOne)',
                'many' => 'One to many (hasMany)',
            ]
        );
    }

    /**
     * Ask user to select a table from existing tables or enter manually.
     *
     * @param  array  $existingTables Available tables
     * @param  string $label          Prompt label
     * @return string Table name
     */
    protected function askForTable(array $existingTables, string $label): string
    {
        if (!empty($existingTables)) {
            $source = array_combine($existingTables, $existingTables)
                    |> (fn($x) => array_merge($x, ['__manual__' => 'Enter manually...']))
                    |> (fn($x) => select(label: $label, options: $x));

            if ($source !== '__manual__') {
                return $source;
            }
        }

        return text(label: 'Table name', required: true);
    }

    /**
     * Ask user to select a column from a list of columns.
     *
     * @param  array  $columns  Available column names
     * @param  string $label    Prompt label
     * @param  string $default  Default column name
     * @return string Selected column name
     */
    protected function askForColumn(array $columns, string $label, string $default = 'id'): string
    {
        if (!empty($columns)) {
            return select(
                label: $label,
                options: array_combine($columns, $columns),
                default: in_array($default, $columns) ? $default : ($columns[0] ?? 'id')
            );
        }

        return text(label: $label, default: $default);
    }

    /**
     * Ask user to select or create a pivot table for many-to-many relationships.
     *
     * @param  array  $existingTables Available tables
     * @param  string $targetTable    Target table name
     * @param  string $currentModel   Current model name
     * @return string Pivot table name
     */
    protected function askForPivotTable(array $existingTables, string $targetTable, string $currentModel): string
    {
        $suggested = $this->buildPivotTableName($targetTable, $currentModel);

        if (!empty($existingTables)) {
            $options = [];

            if (in_array($suggested, $existingTables)) {
                $options[$suggested] = $suggested . ' (recommended)';
            }

            $options = array_merge($options, array_combine($existingTables, $existingTables));
            $options['__manual__'] = 'Enter manually...';

            $source = select(
                label: 'Pivot table',
                options: $options
            );

            if ($source !== '__manual__') {
                return $source;
            }
        }

        return text(label: 'Pivot table name', default: $suggested);
    }

    /**
     * Build a pivot table name from two table names.
     *
     * Uses Laravel convention: alphabetically sorted plural table names joined with underscore.
     * Example: 'posts' and 'tags' become 'posts_tags'
     *
     * @param  string $table1  First table name
     * @param  string $model2  Second model name
     * @return string Pivot table name
     */
    protected function buildPivotTableName(string $table1, string $model2): string
    {
        $tables = [Str::snake(Str::plural($table1)), Str::snake(Str::plural($model2))];
        sort($tables);
        return implode('_', $tables);
    }
}
