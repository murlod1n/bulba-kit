<?php

namespace Nktlksvch\BulbaKit\Generators\Builders;

use Illuminate\Support\Str;

class FieldsJsxBuilder
{
    /**
     * Build JSX code for form fields using shadcn/ui components.
     *
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @return string TSX code with shadcn component markup
     */
    public function build(array $fields, array $relationships): string
    {
        $jsx = '';

        foreach ($fields as $field) {
            $type = $field['type'];
            $fieldName = $field['name'];
            $label = Str::title(str_replace('_', ' ', $fieldName));

            $jsx .= match ($type) {
                'image' => $this->buildImageField($fieldName, $label),
                'gallery' => $this->buildGalleryField($fieldName, $label),
                'text', 'json' => $this->buildTextareaField($fieldName, $label),
                'boolean' => $this->buildCheckboxField($fieldName, $label),
                default => $this->isBelongsToField($fieldName, $relationships)
                    ? $this->buildSelectField($fieldName, $label, $relationships)
                    : $this->buildInputField($fieldName, $label, $type),
            };
        }

        return $jsx;
    }

    protected function buildImageField(string $fieldName, string $label): string
    {
        $jsx = "\n                <ImageUpload\n";
        $jsx .= "                    label=\"{$label}\"\n";
        $jsx .= "                    value={data.{$fieldName}_url}\n";
        $jsx .= "                    thumb={data.{$fieldName}_thumb}\n";
        $jsx .= "                    alt={data.{$fieldName}_alt}\n";
        $jsx .= "                    error={errors.{$fieldName}}\n";
        $jsx .= "                    onChange={(file) => setData('{$fieldName}', file)}\n";
        $jsx .= "                    onRemove={() => setData('remove_{$fieldName}', true)}\n";
        $jsx .= "                    onAltChange={(alt) => setData('{$fieldName}_alt', alt)}\n";
        $jsx .= '                />';

        return $jsx;
    }

    protected function buildGalleryField(string $fieldName, string $label): string
    {
        $jsx = "\n                <GalleryUpload\n";
        $jsx .= "                    label=\"{$label}\"\n";
        $jsx .= "                    value={data.{$fieldName} as GalleryItem[]}\n";
        $jsx .= "                    error={errors.{$fieldName}}\n";
        $jsx .= "                    onChange={(files) => setData('{$fieldName}', files)}\n";
        $jsx .= "                    onRemove={(id) => setData('remove_{$fieldName}', [...(data.remove_{$fieldName} as number[] || []), id])}\n";
        $jsx .= "                    onReorder={(ids) => setData('reorder_{$fieldName}', ids)}\n";
        $jsx .= "                    onAltChange={(id, alt) => setData('alt_{$fieldName}', { ...((data.alt_{$fieldName} as Record<number, string>) || {}), [id]: alt })}\n";
        $jsx .= '                />';

        return $jsx;
    }

    protected function buildTextareaField(string $fieldName, string $label): string
    {
        $jsx = "\n                <Field data-invalid={!!errors.{$fieldName}}>\n";
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

        return $jsx;
    }

    protected function buildCheckboxField(string $fieldName, string $label): string
    {
        $jsx = "\n                <Field data-invalid={!!errors.{$fieldName}} orientation=\"horizontal\">\n";
        $jsx .= "                    <Checkbox\n";
        $jsx .= "                        id=\"{$fieldName}\"\n";
        $jsx .= "                        name=\"{$fieldName}\"\n";
        $jsx .= "                        checked={!!data.{$fieldName}}\n";
        $jsx .= "                        onCheckedChange={(checked) => setData('{$fieldName}', checked)}\n";
        $jsx .= "                    />\n";
        $jsx .= "                    <FieldLabel htmlFor=\"{$fieldName}\" className=\"font-normal\">{$label}</FieldLabel>\n";
        $jsx .= "                    {errors.{$fieldName} && <FieldDescription>{errors.{$fieldName}}</FieldDescription>}\n";
        $jsx .= '                </Field>';

        return $jsx;
    }

    /**
     * @param  array<int, array<string, mixed>>  $relationships
     */
    protected function buildSelectField(string $fieldName, string $label, array $relationships): string
    {
        $relKey = $this->getRelationKeyForField($fieldName, $relationships);

        $jsx = "\n                <Field data-invalid={!!errors.{$fieldName}}>\n";
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

        return $jsx;
    }

    protected function buildInputField(string $fieldName, string $label, string $type): string
    {
        $inputType = $type === 'integer' || $type === 'decimal' ? 'number' : ($type === 'date' ? 'date' : ($type === 'datetime' ? 'datetime-local' : 'text'));
        $stepAttr = $type === 'decimal' ? "\n                        step=\"0.01\"" : '';

        $jsx = "\n                <Field data-invalid={!!errors.{$fieldName}}>\n";
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

        return $jsx;
    }

    /**
     * @param  array<int, array<string, mixed>>  $relationships
     */
    protected function isBelongsToField(string $fieldName, array $relationships): bool
    {
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo' && $rel['foreign_key'] === $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $relationships
     */
    protected function getRelationKeyForField(string $fieldName, array $relationships): string
    {
        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo' && $rel['foreign_key'] === $fieldName) {
                return $rel['name'] ?? str_replace('_id', '', $fieldName);
            }
        }

        return str_replace('_id', '', $fieldName);
    }
}
