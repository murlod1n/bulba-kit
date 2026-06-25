<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Generators;

use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Tests\TestCase;

class ControllerGeneratorTest extends TestCase
{
    private ControllerGenerator $generator;
    private string $controllerDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ControllerGenerator();
        $this->controllerDir = $this->tempDir . '/app/Http/Controllers/Admin';
        mkdir($this->controllerDir, 0755, true);
        $this->app->useAppPath($this->tempDir . '/app');
        $this->app['config']->set('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $this->app['config']->set('bulba.resource_namespace', 'App\\Resources');
        $this->app['config']->set('bulba.react_pages_path', 'admin');
    }

    public function test_generate_creates_controller_file(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $this->assertFileExists($this->controllerDir . '/PostController.php');
    }

    public function test_generate_controller_contains_class_name(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $this->assertFileContains($this->controllerDir . '/PostController.php', 'class PostController extends Controller');
    }

    public function test_generate_inertia_controller_contains_inertia_render(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('Inertia::render', $content);
    }

    public function test_generate_api_controller_returns_json(): void
    {
        $this->generator->generate('Post', 'api', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('response()->json', $content);
    }

    public function test_generate_controller_with_all_methods(): void
    {
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

        $this->generator->generate('Post', 'inertia', $methods);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('public function index(', $content);
        $this->assertStringContainsString('public function create(', $content);
        $this->assertStringContainsString('public function store(', $content);
        $this->assertStringContainsString('public function show(', $content);
        $this->assertStringContainsString('public function edit(', $content);
        $this->assertStringContainsString('public function update(', $content);
        $this->assertStringContainsString('public function destroy(', $content);
    }

    public function test_generate_controller_with_selected_methods(): void
    {
        $this->generator->generate('Post', 'inertia', ['index', 'store']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('public function index(', $content);
        $this->assertStringContainsString('public function store(', $content);
    }

    public function test_generate_controller_with_only_index(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('public function index(', $content);
    }

    public function test_generate_controller_has_resource_dependency(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('PostResource', $content);
    }

    public function test_generate_controller_has_helper_methods(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('getSelectOptions', $content);
        $this->assertStringContainsString('getRelationNames', $content);
        $this->assertStringContainsString('syncBelongsToMany', $content);
    }

    public function test_generate_controller_inertia_index_renders_correct_page(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString("Post/Index", $content);
    }

    public function test_generate_controller_inertia_store_validates_and_creates(): void
    {
        $this->generator->generate('Post', 'inertia', ['store']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('validate', $content);
        $this->assertStringContainsString('create', $content);
    }

    public function test_generate_controller_api_index_returns_paginated(): void
    {
        $this->generator->generate('Post', 'api', ['index']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('paginate', $content);
        $this->assertStringContainsString('response()->json', $content);
    }

    public function test_generate_controller_api_store_returns_201(): void
    {
        $this->generator->generate('Post', 'api', ['store']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('201', $content);
    }

    public function test_generate_controller_api_destroy_returns_204(): void
    {
        $this->generator->generate('Post', 'api', ['destroy']);

        $content = file_get_contents($this->controllerDir . '/PostController.php');
        $this->assertStringContainsString('204', $content);
    }

    public function test_generate_skips_if_controller_exists(): void
    {
        file_put_contents($this->controllerDir . '/PostController.php', '<?php // existing');

        $this->generator->generate('Post', 'inertia', ['index']);

        $this->assertSame('<?php // existing', file_get_contents($this->controllerDir . '/PostController.php'));
    }
}
