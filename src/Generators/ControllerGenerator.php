<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * Controller Generator
 *
 * Generates Laravel controller classes for admin CRUD operations.
 * Supports two controller types:
 * - Inertia: Returns Inertia responses with React page components
 * - API: Returns JSON responses for REST API consumption
 *
 * Each controller method is generated from individual stub files,
 * allowing users to customize method implementations.
 */
class ControllerGenerator
{
    use LoadsStubs;

    /**
     * Generate a new controller class file.
     *
     * @param  string  $name  Model name (e.g., 'Post')
     * @param  string  $type  Controller type ('inertia' or 'api')
     * @param  array<int, string>  $methods  Methods to generate (default: all CRUD methods)
     * @param  array<int, array<string, mixed>>  $fields  Field definitions (for image detection)
     */
    public function generate(
        $name,
        $type = 'inertia',
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'],
        $fields = []
    ): void {
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $controllerPath = app_path(
            str_replace('\\', '/', str_replace('App\\', '', $namespace))."/{$name}Controller.php"
        );

        // Skip if controller already exists
        if (File::exists($controllerPath)) {
            return;
        }

        $stub = $this->getStub('controller-'.$type);
        $resourceNamespace = config('bulba.resource_namespace', 'App\\Resources');
        $pagesPath = config('bulba.react_pages_path', 'admin');

        $imageFields = array_filter($fields, fn ($f) => ($f['type'] ?? '') === 'image');
        $hasMedia = count($imageFields) > 0;

        // Build method contents from individual stubs
        $methodContents = [];
        foreach ($methods as $method) {
            $methodStub = $this->getMethodStub($type, $method, $hasMedia);
            $methodContents[] = str_replace(
                ['{{ model }}', '{{ modelLower }}', '{{ modelLowerPlural }}', '{{ pagesPath }}'],
                [$name, Str::lower($name), Str::lower(Str::plural($name)), $pagesPath],
                $methodStub
            );
        }

        // Add media helper methods if needed
        $mediaMethods = $hasMedia ? $this->buildMediaMethods($imageFields) : '';

        // Replace placeholders in controller stub
        $content = str_replace(
            [
                '{{ namespace }}', '{{ model }}', '{{ modelLower }}',
                '{{ resourceNamespace }}', '{{ resourceClass }}',
                '{{ index }}', '{{ create }}', '{{ store }}',
                '{{ show }}', '{{ edit }}', '{{ update }}', '{{ destroy }}',
                '{{ mediaMethods }}',
            ],
            array_merge(
                [$namespace, $name, Str::lower($name), $resourceNamespace, $name.'Resource'],
                $this->getMethodPlaceholders($methods, $methodContents),
                [$mediaMethods]
            ),
            $stub
        );

        // Add media imports if needed
        if ($hasMedia) {
            $content = str_replace(
                'use Inertia\Inertia;',
                "use Illuminate\Http\UploadedFile;\nuse Inertia\Inertia;",
                $content
            );
        }

        File::ensureDirectoryExists(dirname($controllerPath));
        File::put($controllerPath, $content);
    }

    /**
     * Build method placeholders array for stub replacement.
     *
     * Maps each method name to its content, using empty string for methods
     * not in the selected methods list.
     *
     * @param  array<int, string>  $methods  Selected methods
     * @param  array<int, string>  $methodContents  Generated method contents
     * @return array<int, string> Placeholders indexed by method name
     */
    protected function getMethodPlaceholders(array $methods, array $methodContents): array
    {
        $placeholders = [];
        $allMethods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $methodIndex = 0;

        foreach ($allMethods as $method) {
            if (in_array($method, $methods)) {
                $placeholders[] = $methodContents[$methodIndex];
                $methodIndex++;
            } else {
                $placeholders[] = '';
            }
        }

        return $placeholders;
    }

    /**
     * Get a method stub file content.
     *
     * @param  string  $type  Controller type ('inertia' or 'api')
     * @param  string  $method  Method name
     * @param  bool  $hasMedia  Whether to use media-aware stubs
     * @return string Method stub content
     */
    protected function getMethodStub($type, $method, bool $hasMedia = false): string
    {
        // Use media-aware stubs for store/update when image fields exist
        if ($hasMedia && in_array($method, ['store', 'update'])) {
            $mediaStub = $this->getPackageStubPath("controllers/methods/{$type}-{$method}-media");
            if (File::exists($mediaStub.'.stub')) {
                return File::get($mediaStub.'.stub');
            }
        }

        return $this->getSubStub('controllers/methods', "{$type}-{$method}");
    }

    /**
     * Build media helper methods for controllers with image fields.
     *
     * @param  array<int, array<string, mixed>>  $imageFields  Image field definitions
     * @return string Method code
     */
    protected function buildMediaMethods(array $imageFields): string
    {
        $collections = [];
        foreach ($imageFields as $field) {
            $collection = $field['modifiers']['collection'] ?? $field['name'];
            $collections[$field['name']] = $collection;
        }

        $uploadCases = '';
        foreach ($collections as $fieldName => $collection) {
            $uploadCases .= <<<PHP

        if (\$request->file('{$fieldName}')) {
            \$item->clearMediaCollection('{$collection}');
            \$item->addMedia(\$request->file('{$fieldName}'))
                ->withCustomProperties(['alt' => \$request->input('{$fieldName}_alt', '')])
                ->toMediaCollection('{$collection}');
        }

PHP;
        }

        $removalCases = '';
        foreach ($collections as $fieldName => $collection) {
            $removalCases .= <<<PHP

        if (\$request->input('remove_{$fieldName}')) {
            \$item->clearMediaCollection('{$collection}');
        }

PHP;
        }

        return <<<PHP

    protected function handleMediaUpload(\$item, Request \$request): void
    {
{$uploadCases}    }

    protected function handleMediaRemoval(\$item, Request \$request): void
    {
{$removalCases}    }

    protected function updateMediaAlt(\$item, Request \$request): void
    {
        foreach (\$item->getMedia() as \$media) {
            \$altKey = \$media->collection_name . '_alt';
            if (\$request->has(\$altKey)) {
                \$media->setCustomProperty('alt', \$request->input(\$altKey));
                \$media->save();
            }
        }
    }
PHP;
    }
}
