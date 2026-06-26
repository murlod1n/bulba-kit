<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\Generators\Builders\MediaBuilder;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * Model Generator
 *
 * Generates Eloquent Model classes for admin CRUD resources.
 * Models include:
 * - Fillable attributes
 * - Soft deletes support (optional)
 * - Relationship methods (belongsTo, hasOne, hasMany, belongsToMany)
 */
class ModelGenerator
{
    use LoadsStubs;

    public function __construct(
        private readonly MediaBuilder $mediaBuilder = new MediaBuilder,
    ) {}

    /**
     * Generate a new Model class file.
     *
     * @param  string  $name  Model name (e.g., 'Post')
     * @param  array<int, array<string, mixed>>  $fields  Field definitions from askForFields()
     * @param  bool  $withSoftDeletes  Whether to include SoftDeletes trait
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions from askForRelationships()
     * @param  array<int, string>  $translatableFields  Translatable field names
     */
    public function generate($name, $fields, $withSoftDeletes, $relationships = [], $translatableFields = []): void
    {
        $modelPath = app_path("Models/{$name}.php");

        // Skip if model already exists
        if (File::exists($modelPath)) {
            return;
        }

        $stub = $this->getStub('model');
        $imageFields = array_filter($fields, fn ($f) => ($f['type'] ?? '') === 'image');
        $galleryFields = array_filter($fields, fn ($f) => ($f['type'] ?? '') === 'gallery');
        $hasMedia = count($imageFields) > 0 || count($galleryFields) > 0;
        $hasTranslatable = count($translatableFields) > 0;

        $content = str_replace(
            [
                '{{ name }}',
                '{{ fillable }}',
                '{{ softDeleteTrait }}',
                '{{ softDeleteImport }}',
                '{{ relationImports }}',
                '{{ relations }}',
                '{{ mediaInterface }}',
                '{{ mediaTrait }}',
                '{{ mediaImports }}',
                '{{ mediaCollections }}',
                '{{ mediaConversions }}',
                '{{ mediaAccessors }}',
                '{{ translatableInterface }}',
                '{{ translatableTrait }}',
                '{{ translatableImport }}',
                '{{ translatableProperties }}',
            ],
            [
                $name,
                $this->buildFillableString($fields, $relationships, $translatableFields),
                $this->buildSoftDeleteTrait($withSoftDeletes).($hasMedia ? "    use InteractsWithMedia;\n" : '').($hasTranslatable ? "    use HasTranslations;\n" : ''),
                $this->buildSoftDeleteImport($withSoftDeletes),
                $this->buildRelationImports($relationships),
                $this->buildRelationMethods($relationships),
                $hasMedia ? ' implements HasMedia' : ($hasTranslatable ? ' implements HasTranslatable' : ''),
                '', // trait already added in softDeleteTrait
                $hasMedia ? "\nuse Spatie\\MediaLibrary\\HasMedia;\nuse Spatie\\MediaLibrary\\InteractsWithMedia;\nuse Spatie\\MediaLibrary\\MediaCollections\\Models\\Media;\nuse Spatie\\Image\\Enums\\Fit;" : '',
                $hasMedia ? $this->mediaBuilder->buildMediaCollections($imageFields) : $this->mediaBuilder->buildMediaCollections([]),
                $hasMedia ? $this->mediaBuilder->buildMediaConversions($imageFields) : $this->mediaBuilder->buildMediaConversions([]),
                $hasMedia ? $this->buildMediaAccessors($imageFields, $galleryFields) : '',
                '', // handled by mediaInterface
                '', // handled by mediaTrait
                $hasTranslatable ? "\nuse Spatie\\Translatable\\HasTranslatable;" : '',
                $hasTranslatable ? $this->buildTranslatableProperties($translatableFields) : '',
            ],
            $stub
        );

        File::ensureDirectoryExists(app_path('Models'));
        File::put($modelPath, $content);
    }

    /**
     * Add an inverse relation method to an existing model file.
     *
     * When a new model defines a relationship with an existing model,
     * this method adds the inverse relationship method to the existing model.
     *
     * @param  string  $targetModel  Model to add the relation to
     * @param  string  $relationType  Relation type (belongsTo, hasOne, hasMany, belongsToMany)
     * @param  string  $currentModel  Current model being created
     * @param  string  $fk  Foreign key or pivot table name
     */
    public function addInverseRelation(
        string $targetModel,
        string $relationType,
        string $currentModel,
        string $fk
    ): void {
        $path = app_path("Models/{$targetModel}.php");

        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $methodName = $this->resolveMethodName($relationType, $currentModel);

        // Skip if method already exists
        if (Str::contains($content, "function {$methodName}()")) {
            return;
        }

        // Add model import
        $content = $this->ensureModelImport($content, $currentModel);

        // For belongsTo inverse, add FK to fillable
        if ($relationType === 'belongsTo') {
            $content = $this->addFkToFillable($content, $fk);
        }

        // Add relation method
        $method = $this->buildSingleRelationMethod($methodName, $relationType, $currentModel, $fk);
        $content = preg_replace('/\}\s*$/', $method."\n}", $content);

        File::put($path, $content);
    }

    /**
     * Build the fillable attributes string.
     *
     * Includes user-defined fields (except translatable) and auto-generated FK fields from belongsTo relationships.
     * Translatable fields are excluded from fillable because spatie/laravel-translatable
     * handles them via setTranslation().
     *
     * @param  array<int, array<string, mixed>>  $fields  Field definitions
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @param  array<int, string>  $translatableFields  Translatable field names
     * @return string Comma-separated quoted field names
     */
    protected function buildFillableString(array $fields, array $relationships = [], array $translatableFields = []): string
    {
        $fillable = [];
        foreach ($fields as $f) {
            // Skip translatable fields — spatie handles them via setTranslation
            if (in_array($f['name'], $translatableFields)) {
                continue;
            }
            $fillable[] = "'{$f['name']}'";
        }
        $fieldNames = array_column($fields, 'name');

        foreach ($relationships as $rel) {
            if ($rel['type'] === 'belongsTo' && ! in_array($rel['foreign_key'], $fieldNames)) {
                $fillable[] = "'{$rel['foreign_key']}'";
            }
        }

        return implode(', ', $fillable);
    }

    /**
     * Build the SoftDeletes trait code.
     *
     * @param  bool  $withSoftDeletes  Whether to include SoftDeletes
     * @return string Trait code or empty string
     */
    protected function buildSoftDeleteTrait(bool $withSoftDeletes): string
    {
        return $withSoftDeletes ? "    use SoftDeletes;\n" : '';
    }

    /**
     * Build the SoftDeletes import statement.
     *
     * @param  bool  $withSoftDeletes  Whether to include SoftDeletes
     * @return string Import statement or empty string
     */
    protected function buildSoftDeleteImport(bool $withSoftDeletes): string
    {
        return $withSoftDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
    }

    /**
     * Build model import statements for relationships.
     *
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @return string Import statements
     */
    protected function buildRelationImports(array $relationships): string
    {
        if (empty($relationships)) {
            return '';
        }

        $imports = [];
        foreach ($relationships as $rel) {
            $imports[] = "use App\\Models\\{$rel['target']};";
        }

        return implode("\n", array_unique($imports));
    }

    /**
     * Build all relationship method code blocks.
     *
     * @param  array<int, array<string, mixed>>  $relationships  Relationship definitions
     * @return string Method code blocks
     */
    protected function buildRelationMethods(array $relationships): string
    {
        if (empty($relationships)) {
            return '    // relationships';
        }

        $methods = [];

        foreach ($relationships as $rel) {
            $target = $rel['target'];
            $name = $rel['name'];

            match ($rel['type']) {
                'belongsTo' => $methods[] = $this->buildBelongsTo($name, $target, $rel['foreign_key']),
                'hasOne' => $methods[] = $this->buildHasOne($name, $target, $rel['foreign_key']),
                'hasMany' => $methods[] = $this->buildHasMany($name, $target, $rel['foreign_key']),
                'belongsToMany' => $methods[] = $this->buildBelongsToMany($name, $target, $rel['pivot_table'] ?? ''),
                default => null,
            };
        }

        return implode("\n\n", $methods);
    }

    /**
     * Resolve the method name for a relation.
     *
     * @param  string  $relationType  Relation type
     * @param  string  $modelName  Related model name
     * @return string Method name in camelCase
     */
    protected function resolveMethodName(string $relationType, string $modelName): string
    {
        return in_array($relationType, ['hasMany', 'belongsToMany'])
            ? Str::camel(Str::plural($modelName))
            : Str::camel(Str::singular($modelName));
    }

    /**
     * Add a foreign key field to the model's $fillable array.
     *
     * @param  string  $content  Model file content
     * @param  string  $fk  Foreign key column name
     * @return string Updated content
     */
    protected function addFkToFillable(string $content, string $fk): string
    {
        // Skip if FK already in fillable
        if (Str::contains($content, "'{$fk}'")) {
            return $content;
        }

        // Find the fillable array and append FK
        return preg_replace(
            '/(protected\s+\$fillable\s*=\s*\[)(.*?)(\])/s',
            '$1$2, \''.$fk.'\'$3',
            $content
        );
    }

    /**
     * Ensure the model import statement exists in the file.
     *
     * @param  string  $content  File content
     * @param  string  $modelName  Model to import
     * @return string Updated content
     */
    protected function ensureModelImport(string $content, string $modelName): string
    {
        $import = "use App\\Models\\{$modelName};";

        if (Str::contains($content, $import)) {
            return $content;
        }

        // Try both backslash styles
        foreach (['use Illuminate\\Database\\Eloquent\\Model;', 'use Illuminate\Database\Eloquent\Model;'] as $modelImport) {
            if (Str::contains($content, $modelImport)) {
                return str_replace($modelImport, "{$modelImport}\n{$import}", $content);
            }
        }

        return $content;
    }

    /**
     * Build a single relation method code block.
     *
     * @param  string  $methodName  Method name
     * @param  string  $relationType  Relation type
     * @param  string  $model  Related model name
     * @param  string  $fk  Foreign key or pivot table
     * @return string Method code
     */
    protected function buildSingleRelationMethod(
        string $methodName,
        string $relationType,
        string $model,
        string $fk
    ): string {
        return match ($relationType) {
            'belongsTo' => <<<PHP

    public function {$methodName}()
    {
        return \$this->belongsTo({$model}::class, '{$fk}');
    }
PHP,
            'hasOne' => <<<PHP

    public function {$methodName}()
    {
        return \$this->hasOne({$model}::class, '{$fk}');
    }
PHP,
            'hasMany' => <<<PHP

    public function {$methodName}()
    {
        return \$this->hasMany({$model}::class, '{$fk}');
    }
PHP,
            'belongsToMany' => <<<PHP

    public function {$methodName}()
    {
        return \$this->belongsToMany({$model}::class, '{$fk}');
    }
PHP,
            default => '',
        };
    }

    /**
     * Build a belongsTo relationship method.
     *
     * @param  string  $name  Method name
     * @param  string  $target  Target model name
     * @param  string  $fk  Foreign key column
     * @return string Method code
     */
    protected function buildBelongsTo(string $name, string $target, string $fk): string
    {
        return <<<PHP
    public function {$name}()
    {
        return \$this->belongsTo({$target}::class, '{$fk}');
    }
PHP;
    }

    /**
     * Build a hasOne relationship method.
     *
     * @param  string  $name  Method name
     * @param  string  $target  Target model name
     * @param  string  $fk  Foreign key column
     * @return string Method code
     */
    protected function buildHasOne(string $name, string $target, string $fk): string
    {
        return <<<PHP
    public function {$name}()
    {
        return \$this->hasOne({$target}::class, '{$fk}');
    }
PHP;
    }

    /**
     * Build a hasMany relationship method.
     *
     * @param  string  $name  Method name
     * @param  string  $target  Target model name
     * @param  string  $fk  Foreign key column
     * @return string Method code
     */
    protected function buildHasMany(string $name, string $target, string $fk): string
    {
        return <<<PHP
    public function {$name}()
    {
        return \$this->hasMany({$target}::class, '{$fk}');
    }
PHP;
    }

    /**
     * Build a belongsToMany relationship method.
     *
     * @param  string  $name  Method name
     * @param  string  $target  Target model name
     * @param  string  $pivot  Pivot table name
     * @return string Method code
     */
    protected function buildBelongsToMany(string $name, string $target, string $pivot): string
    {
        return <<<PHP
    public function {$name}()
    {
        return \$this->belongsToMany({$target}::class, '{$pivot}');
    }
PHP;
    }

    /**
     * Build translatable properties for the model.
     *
     * @param  array<int, string>  $translatableFields  Translatable field names
     * @return string Property code
     */
    protected function buildTranslatableProperties(array $translatableFields): string
    {
        if (empty($translatableFields)) {
            return '';
        }

        $fields = implode(', ', array_map(fn ($f) => "'{$f}'", $translatableFields));

        return "    protected array \$translatable = [{$fields}];";
    }

    /**
     * Build accessor methods for image and gallery fields.
     *
     * For image fields: getUrlAttribute(), getThumbAttribute(), getAltAttribute()
     * For gallery fields: getImagesAttribute() returning array of {id, url, thumb, alt}
     *
     * @param  array<int, array<string, mixed>>  $imageFields  Image field definitions
     * @param  array<int, array<string, mixed>>  $galleryFields  Gallery field definitions
     * @return string Accessor methods code
     */
    protected function buildMediaAccessors(array $imageFields, array $galleryFields = []): string
    {
        if (empty($imageFields) && empty($galleryFields)) {
            return '';
        }

        $methods = [];

        foreach ($imageFields as $field) {
            $name = $field['name'];
            $collection = $field['modifiers']['collection'] ?? $name;

            $methods[] = <<<PHP
    public function get{$this->studly($name)}UrlAttribute(): ?string
    {
        return \$this->getFirstMediaUrl('{$collection}') ?: null;
    }

    public function get{$this->studly($name)}ThumbAttribute(): ?string
    {
        return \$this->getFirstMediaUrl('{$collection}', 'thumb') ?: null;
    }

    public function get{$this->studly($name)}AltAttribute(): ?string
    {
        return \$this->getFirstMedia('{$collection}')?->getCustomProperty('alt');
    }
PHP;
        }

        foreach ($galleryFields as $field) {
            $name = $field['name'];
            $collection = $field['modifiers']['collection'] ?? Str::snake(Str::plural($name));

            $methods[] = <<<PHP
    public function get{$this->studly($name)}Attribute(): array
    {
        return \$this->getMedia('{$collection}')->map(fn (\$media) => [
            'id' => \$media->id,
            'url' => \$media->getUrl(),
            'thumb' => \$media->getUrl('thumb'),
            'alt' => \$media->getCustomProperty('alt'),
        ])->toArray();
    }
PHP;
        }

        return implode("\n\n", $methods);
    }

    /**
     * Convert a string to studly caps case.
     */
    protected function studly(string $value): string
    {
        return Str::studly($value);
    }
}
