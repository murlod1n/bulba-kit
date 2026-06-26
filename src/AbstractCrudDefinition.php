<?php

namespace Nktlksvch\BulbaKit;

/**
 * AbstractCrudDefinition
 *
 * Base class for all generated admin resource classes. Defines the contract
 * that each resource must implement to provide metadata for admin CRUD
 * operations including form rendering, validation, and relationship management.
 *
 * Generated resource classes extend this and provide static methods that return
 * structured data used by controllers and frontend components.
 */
abstract class AbstractCrudDefinition
{
    /**
     * Get the Eloquent model class associated with this resource.
     *
     * @return string Fully qualified model class name (e.g., \App\Models\Post::class)
     */
    abstract public static function model(): string;

    /**
     * Get the base route name for this resource.
     *
     * Used for generating redirect routes (e.g., 'admin.posts.index').
     *
     * @return string Base route name (e.g., 'admin.posts')
     */
    abstract public static function routeName(): string;

    /**
     * Get the Inertia page path for this resource.
     *
     * Used for Inertia::render() calls (e.g., 'admin/Post').
     *
     * @return string Page path
     */
    abstract public static function pagePath(): string;

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

    /**
     * Get media collection mappings for this resource.
     *
     * Returns an associative array of field_name => collection_name
     * for resources that have image or gallery fields.
     *
     * @return array<string, string> Associative array of field_name => collection_name
     */
    public static function mediaCollections(): array
    {
        return [];
    }

    /**
     * Get gallery field names for this resource.
     *
     * Returns an array of field names that are galleries (multiple images).
     * Used by HasMediaActions to distinguish single vs multi uploads.
     *
     * @return array<int, string> Array of gallery field names
     */
    public static function mediaGalleries(): array
    {
        return [];
    }
}
