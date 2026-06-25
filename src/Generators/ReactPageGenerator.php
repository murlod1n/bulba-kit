<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    /**
     * Generate all React page components.
     *
     * @param  string  $name  Model name (e.g., 'Post')
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
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
        $fieldsJsx = $this->buildFieldsJsx($fields, $relationships);

        $content = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ fieldsJsx }}'],
            [$name, Str::lower($name), $fieldsJsx],
            $stub
        );

        File::put($pagesDir.'/Form.tsx', $content);
    }

    /**
     * Build JSX code for form fields using shadcn/ui components.
     *
     * Generates appropriate shadcn components based on field type:
     * - text/json: Field + Textarea
     * - boolean: Field (horizontal) + Checkbox
     * - belongsTo: Field + Select with SelectGroup/SelectItem
     * - integer/decimal: Field + Input (type=number)
     * - date/datetime: Field + Input (type=date/datetime-local)
     * - others: Field + Input (type=text)
     *
     * Validation uses data-invalid on Field and aria-invalid on the control.
     *
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @return string TSX code with shadcn component markup
     */
    protected function buildFieldsJsx($fields, $relationships): string
    {
        $jsx = '';

        foreach ($fields as $field) {
            $type = $field['type'];
            $fieldName = $field['name'];
            $label = Str::title(str_replace('_', ' ', $fieldName));

            if ($type === 'text' || $type === 'json') {
                $jsx .= "\n                <Field data-invalid={!!errors.{$fieldName}}>\n";
                $jsx .= "                    <FieldLabel htmlFor=\"{$fieldName}\">{$label}</FieldLabel>\n";
                $jsx .= "                    <Textarea\n";
                $jsx .= "                        id=\"{$fieldName}\"\n";
                $jsx .= "                        name=\"{$fieldName}\"\n";
                $jsx .= "                        value={data.{$fieldName} ?? ''}\n";
                $jsx .= "                        onChange={handleChange}\n";
                $jsx .= "                        aria-invalid={!!errors.{$fieldName}}\n";
                $jsx .= "                        rows={4}\n";
                $jsx .= "                    />\n";
                $jsx .= "                    {errors.{$fieldName} && <FieldDescription>{errors.{$fieldName}}</FieldDescription>}\n";
                $jsx .= '                </Field>';
            } elseif ($type === 'boolean') {
                $jsx .= "\n                <Field data-invalid={!!errors.{$fieldName}} orientation=\"horizontal\">\n";
                $jsx .= "                    <Checkbox\n";
                $jsx .= "                        id=\"{$fieldName}\"\n";
                $jsx .= "                        name=\"{$fieldName}\"\n";
                $jsx .= "                        checked={!!data.{$fieldName}}\n";
                $jsx .= "                        onCheckedChange={(checked) => setData('{$fieldName}', checked)}\n";
                $jsx .= "                    />\n";
                $jsx .= "                    <FieldLabel htmlFor=\"{$fieldName}\" className=\"font-normal\">{$label}</FieldLabel>\n";
                $jsx .= "                    {errors.{$fieldName} && <FieldDescription>{errors.{$fieldName}}</FieldDescription>}\n";
                $jsx .= '                </Field>';
            } elseif ($this->isBelongsToField($fieldName, $relationships)) {
                $relKey = $this->getRelationKeyForField($fieldName, $relationships);
                $jsx .= "\n                <Field data-invalid={!!errors.{$fieldName}}>\n";
                $jsx .= "                    <FieldLabel htmlFor=\"{$fieldName}\">{$label}</FieldLabel>\n";
                $jsx .= "                    <Select\n";
                $jsx .= "                        value={data.{$fieldName} ? String(data.{$fieldName}) : null}\n";
                $jsx .= "                        onValueChange={(value) => setData('{$fieldName}', Number(value))}\n";
                $jsx .= "                    >\n";
                $jsx .= "                        <SelectTrigger id=\"{$fieldName}\" aria-invalid={!!errors.{$fieldName}}>\n";
                $jsx .= "                            <SelectValue placeholder=\"Select {$label}\" />\n";
                $jsx .= "                        </SelectTrigger>\n";
                $jsx .= "                        <SelectContent>\n";
                $jsx .= "                            <SelectGroup>\n";
                $jsx .= "                                {selectOptions.{$relKey} && Object.entries(selectOptions.{$relKey}).map(([id, lbl]) => (\n";
                $jsx .= "                                    <SelectItem key={id} value={String(id)}>{lbl as string}</SelectItem>\n";
                $jsx .= "                                ))}\n";
                $jsx .= "                            </SelectGroup>\n";
                $jsx .= "                        </SelectContent>\n";
                $jsx .= "                    </Select>\n";
                $jsx .= "                    {errors.{$fieldName} && <FieldDescription>{errors.{$fieldName}}</FieldDescription>}\n";
                $jsx .= '                </Field>';
            } else {
                $inputType = $type === 'integer' || $type === 'decimal' ? 'number' : ($type === 'date' ? 'date' : ($type === 'datetime' ? 'datetime-local' : 'text'));
                $stepAttr = $type === 'decimal' ? "\n                        step=\"0.01\"" : '';
                $jsx .= "\n                <Field data-invalid={!!errors.{$fieldName}}>\n";
                $jsx .= "                    <FieldLabel htmlFor=\"{$fieldName}\">{$label}</FieldLabel>\n";
                $jsx .= "                    <Input\n";
                $jsx .= "                        id=\"{$fieldName}\"\n";
                $jsx .= "                        name=\"{$fieldName}\"\n";
                $jsx .= "                        type=\"{$inputType}\"\n";
                $jsx .= "                        value={data.{$fieldName} ?? ''}\n";
                $jsx .= "                        onChange={handleChange}\n";
                $jsx .= "                        aria-invalid={!!errors.{$fieldName}}{$stepAttr}\n";
                $jsx .= "                    />\n";
                $jsx .= "                    {errors.{$fieldName} && <FieldDescription>{errors.{$fieldName}}</FieldDescription>}\n";
                $jsx .= '                </Field>';
            }
        }

        return $jsx;
    }

    /**
     * Check if a field is a belongsTo foreign key.
     *
     * @param  string  $fieldName  Field name
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
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

    /**
     * Get the relation key for a belongsTo foreign key field.
     *
     * @param  string  $fieldName  Field name
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     */
    protected function getRelationKeyForField($fieldName, $relationships): string
    {
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo' && $rel['foreign_key'] === $fieldName) {
                return $rel['name'] ?? str_replace('_id', '', $fieldName);
            }
        }

        return str_replace('_id', '', $fieldName);
    }
}
