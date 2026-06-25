<?php

namespace Nktlksvch\BulbaKit\Services;

use Exception;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Schema Inspector Service
 *
 * Provides database introspection methods for the bulba-kit admin generator.
 * Used to fetch existing tables and columns for relationship configuration.
 */
class SchemaInspector
{
    /**
     * System tables that should be excluded from user selection.
     */
    private const EXCLUDED_TABLES = [
        'migrations',
        'personal_access_tokens',
        'password_reset_tokens',
        'failed_jobs',
        'jobs',
        'cache',
        'cache_locks',
        'sessions',
    ];

    /**
     * Get all user-defined tables from the database.
     *
     * Filters out system tables (migrations, cache, sessions, etc.)
     * and returns table names sorted alphabetically.
     *
     * @return array<int, string> List of table names
     */
    public function getExistingTables(): array
    {
        try {
            return collect(Schema::getTables())
                ->pluck('name')
                ->filter(fn($t) => !in_array($t, self::EXCLUDED_TABLES))
                ->sort()
                ->values()
                ->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get column names for a given table.
     *
     * @param  string $table Table name
     * @return array<int, string>  List of column names
     */
    public function getTableColumns(string $table): array
    {
        try {
            return collect(Schema::getColumns($table))
                ->pluck('name')
                ->toArray();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Detect a suitable display field from table columns.
     *
     * Checks for common display field names in order:
     * 'name', 'title', 'email', 'label', then falls back to 'id'.
     *
     * @param  string $table Table name
     * @return string Display field name
     */
    public function detectDisplayField(string $table): string
    {
        $columns = $this->getTableColumns($table);
        $candidates = ['name', 'title', 'email', 'label'];

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns)) {
                return $candidate;
            }
        }

        return 'id';
    }

    /**
     * Find existing FK columns in a table (columns ending with '_id').
     *
     * @param  string $table Table name
     * @return array<int, string>  List of FK column names
     */
    public function getForeignKeyColumns(string $table): array
    {
        $columns = $this->getTableColumns($table);
        return array_filter($columns, fn($c) => str_ends_with($c, '_id'));
    }

    /**
     * Check if a table exists in the database.
     *
     * @param  string $table Table name
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Check if a column exists in a table.
     *
     * @param  string $table  Table name
     * @param  string $column Column name
     * @return bool
     */
    public function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}
