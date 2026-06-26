<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\Generators\Builders\FieldsJsxBuilder;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * React Page Generator
 *
 * Generates React (TSX) page components for Inertia.js admin interfaces
 * using shadcn/ui components for consistent, accessible UI.
 *
 * Generates:
 * - Index page: Data table with pagination, dropdown actions, delete confirmation
 * - Create page: Card-based form for creating new records
 * - Edit page: Card-based form for editing existing records
 * - Show page: Card-based detail view with relationship tables and badges
 * - Form component: Reusable form with shadcn Field/Input/Textarea/Select/Checkbox
 *
 * All pages use semantic Tailwind CSS tokens (no manual dark: overrides).
 */
class ReactPageGenerator
{
    use LoadsStubs;

    public function __construct(
        private readonly FieldsJsxBuilder $fieldsJsxBuilder = new FieldsJsxBuilder,
    ) {}

    /**
     * Generate all React page components.
     *
     * @param  string  $name  Model name (e.g., 'Post')
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     */
    public function generate($name, $fields, $relationships): void
    {
        $pagesPath = config('bulba.react_pages_path', 'admin');
        $pagesDir = resource_path("js/pages/{$pagesPath}/{$name}");
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
     * @param  string  $name  Model name
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  string  $pagesDir  Pages directory path
     */
    protected function generateIndex($name, $fields, $pagesDir): void
    {
        $stub = $this->getStub('index-page');
        $fieldNames = array_map(fn ($f) => $f['name'], $fields);

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ fieldNames }}'],
            [$name, Str::lower($name), implode(', ', $fieldNames)],
            $stub
        );

        File::put($pagesDir.'/Index.tsx', $content);
    }

    /**
     * Generate the Create page component.
     *
     * @param  string  $name  Model name
     * @param  string  $pagesDir  Pages directory path
     */
    protected function generateCreate($name, $pagesDir): void
    {
        $stub = $this->getStub('create-page');

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}'],
            [$name, Str::lower($name)],
            $stub
        );

        File::put($pagesDir.'/Create.tsx', $content);
    }

    /**
     * Generate the Edit page component.
     *
     * @param  string  $name  Model name
     * @param  string  $pagesDir  Pages directory path
     */
    protected function generateEdit($name, $pagesDir): void
    {
        $stub = $this->getStub('edit-page');

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}'],
            [$name, Str::lower($name)],
            $stub
        );

        File::put($pagesDir.'/Edit.tsx', $content);
    }

    /**
     * Generate the Show page component.
     *
     * @param  string  $name  Model name
     * @param  string  $pagesDir  Pages directory path
     */
    protected function generateShow($name, $pagesDir): void
    {
        $stub = $this->getStub('show-page');

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}'],
            [$name, Str::lower($name)],
            $stub
        );

        File::put($pagesDir.'/Show.tsx', $content);
    }

    /**
     * Generate the Form component.
     *
     * @param  string  $name  Model name
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @param  string  $pagesDir  Pages directory path
     */
    protected function generateForm($name, $fields, $relationships, $pagesDir): void
    {
        $stub = $this->getStub('form-component');
        $fieldsJsx = $this->fieldsJsxBuilder->build($fields, $relationships);

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ fieldsJsx }}'],
            [$name, Str::lower($name), $fieldsJsx],
            $stub
        );

        File::put($pagesDir.'/Form.tsx', $content);
    }
}
