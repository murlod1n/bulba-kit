<?php

namespace Nktlksvch\BulbaKit;

/**
 * AbstractResource
 *
 * Base class for all generated admin resource classes. Defines the contract
 * that each resource must implement to provide metadata for admin CRUD
 * operations including form rendering, validation, and relationship management.
 *
 * Generated resource classes extend this and provide static methods that return
 * structured data used by controllers and frontend components.
 */
abstract class AbstractResource
{
    /**
     * Get the Eloquent model class associated with this resource.
     *
     * @return string Fully qualified model class name (e.g., \App\Models\Post::class)
     */
    abstract public static function model(): string;

    /**
     * Get the field definitions for form rendering.
     *
     * Each field is an array with keys: name, type, label, and optional nullable/unique.
     *
     * @return array<int, array<string, mixed>> Array of field descriptor arrays
     */
    abstract public static function fields(): array;

    /**
     * Get the validation rules for form submission.
     *
     * Returns an associative array of field_name => rules for use with
     * Laravel's Validator or $request->validate().
     *
     * @return array<string, array<int, string>> Associative array of validation rules
     */
    abstract public static function validationRules(): array;

    /**
     * Get the relationship metadata for relation display and management.
     *
     * Each relation is an array with keys: type, model, display_field,
     * and optionally foreign_key or pivot_table.
     *
     * @return array<string, array<string, mixed>> Associative array of relation_name => metadata
     */
    abstract public static function relations(): array;
}
