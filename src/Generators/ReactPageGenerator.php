<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * React Page Generator
 *
 * Generates React (JSX) page components for Inertia.js admin interfaces.
 * Generates:
 * - Index page: Data table with pagination
 * - Create page: Form for creating new records
 * - Edit page: Form for editing existing records
 * - Show page: Display record details with relationships
 * - Form component: Reusable form with field type detection
 *
 * Supports dark mode via Tailwind CSS dark: variants.
 *
 * @package Nktlksvch\BulbaKit\Generators
 */
class ReactPageGenerator
{
    use LoadsStubs;
    /**
     * Generate all React page components.
     *
     * @param  string $name          Model name (e.g., 'Post')
     * @param  array  $fields        Field definitions
     * @param  array  $relationships Relationship definitions
     * @return void
     */
    public function generate($name, $fields, $relationships): void
    {
        $pagesPath = config('bulba.react_pages_path', 'Admin');
        $pagesDir = resource_path("js/Pages/{$pagesPath}/{$name}");
        File::ensureDirectoryExists($pagesDir);

        $this->generateIndex($name, $fields, $pagesDir);
        $this->generateCreate($name, $pagesDir);
        $this->generateEdit($name, $pagesDir);
        $this->generateShow($name, $pagesDir);
        $this->generateForm($name, $fields, $relationships, $pagesDir);
    }

    /**
     * Generate the Index page component.
     *
     * @param  string $name      Model name
     * @param  array  $fields    Field definitions
     * @param  string $pagesDir  Pages directory path
     * @return void
     */
    protected function generateIndex($name, $fields, $pagesDir): void
    {
        $stub = $this->getStub('index-page');
        $fieldNames = array_map(fn($f) => $f['name'], $fields);

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ fieldNames }}'],
            [$name, Str::lower($name), implode(', ', $fieldNames)],
            $stub
        );

        File::put($pagesDir . '/Index.jsx', $content);
    }

    /**
     * Generate the Create page component.
     *
     * @param  string $name     Model name
     * @param  string $pagesDir Pages directory path
     * @return void
     */
    protected function generateCreate($name, $pagesDir): void
    {
        $stub = $this->getStub('create-page');

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}'],
            [$name, Str::lower($name)],
            $stub
        );

        File::put($pagesDir . '/Create.jsx', $content);
    }

    /**
     * Generate the Edit page component.
     *
     * @param  string $name     Model name
     * @param  string $pagesDir Pages directory path
     * @return void
     */
    protected function generateEdit($name, $pagesDir): void
    {
        $stub = $this->getStub('edit-page');

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}'],
            [$name, Str::lower($name)],
            $stub
        );

        File::put($pagesDir . '/Edit.jsx', $content);
    }

    /**
     * Generate the Show page component.
     *
     * @param  string $name     Model name
     * @param  string $pagesDir Pages directory path
     * @return void
     */
    protected function generateShow($name, $pagesDir): void
    {
        $stub = $this->getStub('show-page');

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}'],
            [$name, Str::lower($name)],
            $stub
        );

        File::put($pagesDir . '/Show.jsx', $content);
    }

    /**
     * Generate the Form component.
     *
     * @param  string $name          Model name
     * @param  array  $fields        Field definitions
     * @param  array  $relationships Relationship definitions
     * @param  string $pagesDir      Pages directory path
     * @return void
     */
    protected function generateForm($name, $fields, $relationships, $pagesDir): void
    {
        $stub = $this->getStub('form-component');
        $fieldsJsx = $this->buildFieldsJsx($fields, $relationships);

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ fieldsJsx }}'],
            [$name, Str::lower($name), $fieldsJsx],
            $stub
        );

        File::put($pagesDir . '/Form.jsx', $content);
    }

    /**
     * Build JSX code for form fields.
     *
     * Generates appropriate input elements based on field type:
     * - text/json: textarea
     * - boolean: checkbox
     * - belongsTo: select dropdown
     * - others: text input
     *
     * @param  array $fields        Field definitions
     * @param  array $relationships Relationship definitions
     * @return string JSX code
     */
    protected function buildFieldsJsx($fields, $relationships): string
    {
        $jsx = '';

        foreach ($fields as $field) {
            $type = $field['type'];
            $fieldName = $field['name'];
            $label = Str::title(str_replace('_', ' ', $fieldName));

            $jsx .= "\n                    <div className=\"mb-4\">\n";
            $jsx .= "                        <label className=\"block font-medium\">{$label}</label>\n";

            if ($type === 'text' || $type === 'json') {
                $jsx .= "                        <textarea name=\"{$fieldName}\" value={data.{$fieldName}} onChange={handleChange} className=\"w-full border rounded p-2\"></textarea>\n";
            } elseif ($type === 'boolean') {
                $jsx .= "                        <input type=\"checkbox\" name=\"{$fieldName}\" checked={data.{$fieldName}} onChange={handleChange} />\n";
            } elseif ($this->isBelongsToField($fieldName, $relationships)) {
                $jsx .= "                        <select name=\"{$fieldName}\" value={data.{$fieldName}} onChange={handleChange} className=\"w-full border rounded p-2\">\n";
                $jsx .= "                            <option value=\"\">Select...</option>\n";
                $jsx .= "                            {selectOptions.{$fieldName} && Object.entries(selectOptions.{$fieldName}).map(([id, label]) => (\n";
                $jsx .= "                                <option key={id} value={id}>{label}</option>\n";
                $jsx .= "                            ))}\n";
                $jsx .= "                        </select>\n";
            } else {
                $jsx .= "                        <input type=\"text\" name=\"{$fieldName}\" value={data.{$fieldName}} onChange={handleChange} className=\"w-full border rounded p-2\" />\n";
            }

            $jsx .= "                        {errors.{$fieldName} && <div className=\"text-red-600\">{errors.{$fieldName}}</div>}\n";
            $jsx .= "                    </div>";
        }

        return $jsx;
    }

    /**
     * Check if a field is a belongsTo foreign key.
     *
     * @param  string $fieldName     Field name
     * @param  array  $relationships Relationship definitions
     * @return bool
     */
    protected function isBelongsToField($fieldName, $relationships): bool
    {
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo' && $rel['foreign_key'] === $fieldName) {
                return true;
            }
        }
        return false;
    }
}
