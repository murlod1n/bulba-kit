<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

class ControllerGenerator
{
    use LoadsStubs;

    /**
     * Trait name map: [type][method] => full trait class name.
     */
    private const TRAIT_MAP = [
        'inertia' => [
            'index' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaIndexAction',
            'create' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaCreateAction',
            'store' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaStoreAction',
            'show' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaShowAction',
            'edit' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaEditAction',
            'update' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaUpdateAction',
            'destroy' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Inertia\\HasInertiaDestroyAction',
        ],
        'api' => [
            'index' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiIndexAction',
            'create' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiCreateAction',
            'store' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiStoreAction',
            'show' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiShowAction',
            'edit' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiEditAction',
            'update' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiUpdateAction',
            'destroy' => 'Nktlksvch\\BulbaKit\\Traits\\Actions\\Api\\HasApiDestroyAction',
        ],
    ];

    /**
     * Generate a new controller class file.
     *
     * @param  string  $name  Model name (e.g., 'Post')
     * @param  string  $type  Controller type ('inertia' or 'api')
     * @param  array<int, string>  $methods  Methods to generate
     * @param  array<int, array<string, mixed>>  $fields  Field definitions (for image detection)
     * @param  array<int, string>  $translatableFields  Translatable field names
     */
    public function generate(
        $name,
        $type = 'inertia',
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'],
        $fields = [],
        $translatableFields = []
    ): void {
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $controllerPath = app_path(
            str_replace('\\', '/', str_replace('App\\', '', $namespace)))."/{$name}Controller.php";

        if (File::exists($controllerPath)) {
            return;
        }

        $stub = $this->getStub('controller-'.$type);
        $definitionClass = $name.'CrudDefinition';
        $resourceNamespace = config('bulba.resource_namespace', 'App\\Resources');

        $imageFields = array_filter($fields, fn ($f) => in_array($f['type'] ?? '', ['image', 'gallery']));
        $hasMedia = count($imageFields) > 0;
        $hasTranslatable = count($translatableFields) > 0;

        [$traitImports, $traitUses] = $this->buildTraitStatements($type, $methods, $hasMedia, $hasTranslatable);
        $mediaOverrides = $hasMedia
            ? $this->buildMediaOverrides($type, $methods)
            : '';

        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ model }}',
                '{{ definitionClass }}',
                '{{ resourceNamespace }}',
                '{{ traitImports }}',
                '{{ traitUses }}',
                '{{ mediaOverrides }}',
            ],
            [
                $namespace,
                $name,
                $definitionClass,
                $resourceNamespace,
                $traitImports,
                $traitUses,
                $mediaOverrides,
            ],
            $stub
        );

        File::ensureDirectoryExists(dirname($controllerPath));
        File::put($controllerPath, $content);
    }

    /**
     * Build trait import and use statements.
     *
     * @param  string  $type  Controller type
     * @param  array<int, string>  $methods  Selected methods
     * @param  bool  $hasMedia  Whether media fields exist
     * @param  bool  $hasTranslatable  Whether translatable fields exist
     * @return array{0: string, 1: string} [import statements, use statement]
     */
    protected function buildTraitStatements(string $type, array $methods, bool $hasMedia, bool $hasTranslatable = false): array
    {
        $shortNames = [];
        $imports = [];

        foreach ($methods as $method) {
            $fqcn = self::TRAIT_MAP[$type][$method] ?? null;
            if ($fqcn === null) {
                continue;
            }

            $shortName = class_basename($fqcn);
            $shortNames[] = $shortName;
            $imports[] = 'use '.$fqcn.';';
        }

        if ($hasMedia) {
            $shortNames[] = 'HasMediaActions';
            $imports[] = 'use Nktlksvch\\BulbaKit\\Traits\\HasMediaActions;';
        }

        if ($hasTranslatable) {
            $shortNames[] = 'HasTranslationHelpers';
            $imports[] = 'use Nktlksvch\\BulbaKit\\Traits\\HasTranslationHelpers;';
        }

        $useStatements = implode("\n", array_map(fn ($t) => "    use {$t};", $shortNames));

        return [
            implode("\n", $imports),
            $useStatements,
        ];
    }

    /**
     * Build media override methods for store/update.
     *
     * @param  string  $type  Controller type
     * @param  array<int, string>  $methods  Selected methods
     * @return string Override method code
     */
    protected function buildMediaOverrides(string $type, array $methods): string
    {
        $overrides = [];

        if (in_array('store', $methods)) {
            $overrides[] = $this->getSubStub(
                'media-overrides',
                $type.'-store-media'
            );
        }

        if (in_array('update', $methods)) {
            $overrides[] = $this->getSubStub(
                'media-overrides',
                $type.'-update-media'
            );
        }

        return implode("\n", $overrides);
    }
}
