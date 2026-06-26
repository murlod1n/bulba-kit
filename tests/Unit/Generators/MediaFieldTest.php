<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Generators;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\CrudDefinitionGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Tests\TestCase;

class MediaFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/bulba_media_test_'.uniqid();
        File::makeDirectory($this->tempDir, 0755, true);

        $this->app->useAppPath($this->tempDir.'/app');
        $this->app['config']->set('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $this->app['config']->set('bulba.resource_namespace', 'App\\Resources');
        $this->app['config']->set('bulba.react_pages_path', 'admin');

        File::ensureDirectoryExists($this->tempDir.'/app/Models');
        File::ensureDirectoryExists($this->tempDir.'/app/Resources');
        File::ensureDirectoryExists($this->tempDir.'/app/Http/Controllers/Admin');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }

    protected function fieldsWithImage(): array
    {
        return [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'image', 'type' => 'image', 'modifiers' => ['collection' => 'image', 'thumb_width' => 200, 'thumb_height' => 200, 'single' => true]],
            ['name' => 'is_published', 'type' => 'boolean', 'modifiers' => ['default' => true]],
        ];
    }

    public function test_model_generator_adds_has_media_interface(): void
    {
        app(ModelGenerator::class)->generate('Article', $this->fieldsWithImage(), false, []);

        $modelPath = $this->tempDir.'/app/Models/Article.php';
        $this->assertFileExists($modelPath);

        $content = File::get($modelPath);
        $this->assertStringContainsString('implements HasMedia', $content);
        $this->assertStringContainsString('use InteractsWithMedia;', $content);
        $this->assertStringContainsString('use Spatie\MediaLibrary\HasMedia;', $content);
        $this->assertStringContainsString('use Spatie\MediaLibrary\InteractsWithMedia;', $content);
        $this->assertStringContainsString('use Spatie\MediaLibrary\MediaCollections\Models\Media;', $content);
        $this->assertStringContainsString('use Spatie\Image\Enums\Fit;', $content);
    }

    public function test_model_generator_adds_media_collections(): void
    {
        app(ModelGenerator::class)->generate('Article', $this->fieldsWithImage(), false, []);

        $content = File::get($this->tempDir.'/app/Models/Article.php');
        $this->assertStringContainsString('registerMediaCollections', $content);
        $this->assertStringContainsString("addMediaCollection('image')->singleFile()", $content);
    }

    public function test_model_generator_adds_media_conversions(): void
    {
        app(ModelGenerator::class)->generate('Article', $this->fieldsWithImage(), false, []);

        $content = File::get($this->tempDir.'/app/Models/Article.php');
        $this->assertStringContainsString('registerMediaConversions', $content);
        $this->assertStringContainsString("addMediaConversion('thumb')", $content);
        $this->assertStringContainsString('Fit::Contain, 200, 200', $content);
        $this->assertStringContainsString("addMediaConversion('webp')", $content);
    }

    public function test_model_generator_without_image_fields_has_no_media(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        app(ModelGenerator::class)->generate('Post', $fields, false, []);

        $content = File::get($this->tempDir.'/app/Models/Post.php');
        $this->assertStringNotContainsString('HasMedia', $content);
        $this->assertStringNotContainsString('InteractsWithMedia', $content);
        $this->assertStringContainsString('// media collections', $content);
        $this->assertStringContainsString('// media conversions', $content);
    }

    public function test_resource_generator_includes_image_field(): void
    {
        app(CrudDefinitionGenerator::class)->generate('Article', $this->fieldsWithImage(), []);

        $resourcePath = $this->tempDir.'/app/Resources/ArticleCrudDefinition.php';
        $this->assertFileExists($resourcePath);

        $content = File::get($resourcePath);
        $this->assertStringContainsString("'name' => 'image'", $content);
        $this->assertStringContainsString("'type' => 'image'", $content);
        $this->assertStringContainsString("'collection' => 'image'", $content);
    }

    public function test_controller_generator_uses_media_stubs(): void
    {
        app(ControllerGenerator::class)->generate('Article', 'inertia', ['index', 'create', 'store', 'edit', 'update', 'destroy'], $this->fieldsWithImage());

        $controllerPath = $this->tempDir.'/app/Http/Controllers/Admin/ArticleController.php';
        $this->assertFileExists($controllerPath);

        $content = File::get($controllerPath);
        $this->assertStringContainsString('handleMediaUpload', $content);
        $this->assertStringContainsString('handleMediaRemoval', $content);
        $this->assertStringContainsString('HasMediaActions', $content);
    }

    public function test_controller_generator_without_image_fields_has_no_media(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        app(ControllerGenerator::class)->generate('Post', 'inertia', ['index', 'store', 'update'], $fields);

        $content = File::get($this->tempDir.'/app/Http/Controllers/Admin/PostController.php');
        $this->assertStringNotContainsString('handleMediaUpload', $content);
        $this->assertStringNotContainsString('HasMediaActions', $content);
    }
}
