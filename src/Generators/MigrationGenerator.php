<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * Migration Generator
 *
 * Generates Laravel database migrations for admin CRUD resources.
 * Supports:
 * - CREATE migrations with field definitions and FK constraints
 * - ALTER migrations for adding FK columns to existing tables
 * - Pivot table migrations for many-to-many relationships
 *
 * @package Nktlksvch\BulbaKit\Generators
 */
class MigrationGenerator
{
    use LoadsStubs;
    /**
     * Generate a new CREATE migration file.
     *
     * @param  string $name            Model/table name
     * @param  array  $fields          Field definitions from askForFields()
     * @param  array  $aiFields        AI generation field configs
     * @param  bool   $withTimestamps  Whether to include timestamps
     * @param  bool   $withSoftDeletes Whether to include soft deletes
     * @param  array  $relationships   Relationship definitions (for FK constraints)
     * @return void
     */
    public function generate(
        $name,
        $fields,
        $aiFields,
        $withTimestamps,
        $withSoftDeletes,
        $relationships = []
    ): void {
        $table = Str::snake(Str::plural($name));
        $stub = $this->getStub('migration');

        $migrationContent = str_replace(
            [
                '{{ table }}',
                '{{ fields }}',
                '{{ foreignKeys }}',
                '{{ timestamps }}',
                '{{ softDeletes }}',
            ],
            [
                $table,
                $this->buildFieldDefinitions($fields, $aiFields),
                $this->buildForeignKeys($relationships),
                $withTimestamps ? '$table->timestamps();' : '',
                $withSoftDeletes ? '$table->softDeletes();' : '',
            ],
            $stub
        );

        $filename = date('Y_m_d_His') . '_create_' . $table . '_table.php';
        $path = database_path('migrations/' . $filename);
        File::put($path, $migrationContent);

        // Generate pivot table migrations for belongsToMany relationships
        $this->generatePivotMigrations($relationships);
    }

    /**
     * Generate an ALTER migration to add a FK column to an existing table.
     *
     * Used when the FK is on the target table (not the current one).
     * Always creates nullable columns to avoid issues with existing data.
     *
     * @param  string $targetModel  Target model name (table to alter)
     * @param  string $currentModel Current model name (source of the FK)
     * @param  string $fk           Foreign key column name
     * @param  bool   $cascade      Whether to cascade on delete
     * @return void
     */
    public function addForeignKeyToExisting(
        string $targetModel,
        string $currentModel,
        string $fk,
        bool $cascade = false
    ): void {
        $targetTable = Str::snake(Str::plural($targetModel));
        $fkTable = Str::snake(Str::plural($currentModel));

        // Skip if table doesn't exist
        if (!Schema::hasTable($targetTable)) {
            return;
        }

        // Skip if column already exists
        if (Schema::hasColumn($targetTable, $fk)) {
            return;
        }

        $stub = $this->getStub('migration-alter');

        $content = str_replace(
            ['{{ table }}', '{{ fk }}', '{{ fk_table }}', '{{ cascade }}'],
            [
                $targetTable,
                $fk,
                $fkTable,
                $cascade ? '->cascadeOnDelete()' : '',
            ],
            $stub
        );

        $filename = date('Y_m_d_His') . '_add_' . $fk . '_to_' . $targetTable . '_table.php';
        $path = database_path('migrations/' . $filename);
        File::put($path, $content);
    }

    /**
     * Build field definition lines for the migration.
     *
     * @param  array $fields   Field definitions
     * @param  array $aiFields AI generation field configs
     * @return string Field definition code
     */
    protected function buildFieldDefinitions(array $fields, array $aiFields): string
    {
        $fieldDefinitions = '';

        foreach ($fields as $field) {
            $type = $field['type'];
            $mods = $field['modifiers'];

            // Build column definition
            if (isset($mods['length'])) {
                $line = "\$table->{$type}('{$field['name']}', {$mods['length']})";
            } else {
                $line = "\$table->{$type}('{$field['name']}')";
            }

            // Add modifiers
            if (isset($mods['precision'])) {
                $line .= "->total({$mods['precision']})->places({$mods['scale']})";
            }
            if (isset($mods['nullable'])) {
                $line .= "->nullable()";
            }
            if (isset($mods['unique'])) {
                $line .= "->unique()";
            }

            // Add AI comment if configured
            $aiField = collect($aiFields)->firstWhere('field', $field['name']);
            if ($aiField) {
                $comment = "ai_generate: true; prompt: " . addslashes($aiField['prompt']);
                $line .= "->comment('" . $comment . "')";
            }

            $line .= ';';
            $fieldDefinitions .= "            {$line}\n";
        }

        return $fieldDefinitions;
    }

    /**
     * Build foreign key constraint lines for belongsTo relationships.
     *
     * @param  array $relationships Relationship definitions
     * @return string Foreign key code
     */
    protected function buildForeignKeys(array $relationships): string
    {
        $lines = [];

        foreach ($relationships as $rel) {
            if ($rel['type'] !== 'belongsTo') {
                continue;
            }

            $fk = $rel['foreign_key'];
            $targetTable = Str::snake(Str::plural($rel['target']));
            $nullable = $rel['nullable'] ?? false;
            $cascade = $rel['cascade_on_delete'] ?? false;

            $line = "\$table->foreignId('{$fk}')";

            if ($nullable) {
                $line .= "->nullable()";
            }

            $line .= "->constrained('{$targetTable}')";

            if ($cascade) {
                $line .= "->cascadeOnDelete()";
            }

            $line .= ';';
            $lines[] = "            {$line}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate pivot table migrations for belongsToMany relationships.
     *
     * Creates a separate migration for each pivot table with:
     * - Two foreign key columns
     * - Cascade on delete
     * - Unique constraint on both FKs
     *
     * @param  array $relationships Relationship definitions
     * @return void
     */
    protected function generatePivotMigrations(array $relationships): void
    {
        foreach ($relationships as $rel) {
            if ($rel['type'] !== 'belongsToMany') {
                continue;
            }

            $pivotTable = $rel['pivot_table'];
            $tables = $rel['pivot_tables'] ?? [];

            if (count($tables) < 2) {
                continue;
            }

            $table1 = $tables[0];
            $table2 = $tables[1];
            $fk1 = Str::singular($table1) . '_id';
            $fk2 = Str::singular($table2) . '_id';

            $stub = $this->getStub('migration-pivot');

            $content = str_replace(
                ['{{ table }}', '{{ fk1 }}', '{{ fk1_table }}', '{{ fk2 }}', '{{ fk2_table }}'],
                [$pivotTable, $fk1, $table1, $fk2, $table2],
                $stub
            );

            $filename = date('Y_m_d_His') . '_create_' . $pivotTable . '_table.php';
            $path = database_path('migrations/' . $filename);
            File::put($path, $content);
        }
    }
}
