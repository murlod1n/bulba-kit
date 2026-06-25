<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
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
 *
 * @package Nktlksvch\BulbaKit\Generators
 */
class ControllerGenerator
{
    use LoadsStubs;
    /**
     * Generate a new controller class file.
     *
     * @param  string $name            Model name (e.g., 'Post')
     * @param  string $type            Controller type ('inertia' or 'api')
     * @param  array  $methods         Methods to generate (default: all CRUD methods)
     * @return void
     */
    public function generate(
        $name,
        $type = 'inertia',
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
    ): void {
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $controllerPath = app_path(
            str_replace('\\', '/', str_replace('App\\', '', $namespace)) . "/{$name}Controller.php"
        );

        // Skip if controller already exists
        if (File::exists($controllerPath)) {
            return;
        }

        $stub = $this->getStub('controller-' . $type);
        $resourceNamespace = config('bulba.resource_namespace', 'App\\Resources');
        $pagesPath = config('bulba.react_pages_path', 'Admin');

        // Build method contents from individual stubs
        $methodContents = [];
        foreach ($methods as $method) {
            $methodStub = $this->getMethodStub($type, $method);
            $methodContents[] = str_replace(
                ['{{ model }}', '{{ modelLower }}', '{{ modelLowerPlural }}', '{{ pagesPath }}'],
                [$name, Str::lower($name), Str::lower(Str::plural($name)), $pagesPath],
                $methodStub
            );
        }

        // Replace placeholders in controller stub
        $content = str_replace(
            [
                '{{ namespace }}', '{{ model }}', '{{ modelLower }}',
                '{{ resourceNamespace }}', '{{ resourceClass }}',
                '{{ index }}', '{{ create }}', '{{ store }}',
                '{{ show }}', '{{ edit }}', '{{ update }}', '{{ destroy }}',
            ],
            array_merge(
                [$namespace, $name, Str::lower($name), $resourceNamespace, $name . 'Resource'],
                $this->getMethodPlaceholders($methods, $methodContents)
            ),
            $stub
        );

        File::ensureDirectoryExists(dirname($controllerPath));
        File::put($controllerPath, $content);
    }

    /**
     * Build method placeholders array for stub replacement.
     *
     * Maps each method name to its content, using empty string for methods
     * not in the selected methods list.
     *
     * @param  array $methods        Selected methods
     * @param  array $methodContents Generated method contents
     * @return array Placeholders indexed by method name
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
     * @param  string $type   Controller type ('inertia' or 'api')
     * @param  string $method Method name
     * @return string Method stub content
     */
    protected function getMethodStub($type, $method): string
    {
        return $this->getSubStub('controllers/methods', "{$type}-{$method}");
    }
}
