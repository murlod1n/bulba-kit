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
        $this->generator = new ControllerGenerator;
        $this->controllerDir = $this->tempDir.'/app/Http/Controllers/Admin';
        mkdir($this->controllerDir, 0755, true);
        $this->app->useAppPath($this->tempDir.'/app');
        $this->app['config']->set('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $this->app['config']->set('bulba.resource_namespace', 'App\\Resources');
        $this->app['config']->set('bulba.react_pages_path', 'admin');
    }

    public function test_generate_creates_controller_file(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $this->assertFileExists($this->controllerDir.'/PostController.php');
    }

    public function test_generate_controller_contains_class_name(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $this->assertFileContains($this->controllerDir.'/PostController.php', 'class PostController extends Controller');
    }

    public function test_generate_inertia_controller_uses_inertia_trait(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasInertiaIndexAction', $content);
        $this->assertStringNotContainsString('HasApiIndexAction', $content);
    }

    public function test_generate_api_controller_uses_api_trait(): void
    {
        $this->generator->generate('Post', 'api', ['index']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasApiIndexAction', $content);
        $this->assertStringNotContainsString('HasInertiaIndexAction', $content);
    }

    public function test_generate_controller_with_all_methods(): void
    {
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

        $this->generator->generate('Post', 'inertia', $methods);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasInertiaIndexAction', $content);
        $this->assertStringContainsString('HasInertiaCreateAction', $content);
        $this->assertStringContainsString('HasInertiaStoreAction', $content);
        $this->assertStringContainsString('HasInertiaShowAction', $content);
        $this->assertStringContainsString('HasInertiaEditAction', $content);
        $this->assertStringContainsString('HasInertiaUpdateAction', $content);
        $this->assertStringContainsString('HasInertiaDestroyAction', $content);
    }

    public function test_generate_controller_with_selected_methods(): void
    {
        $this->generator->generate('Post', 'inertia', ['index', 'store']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasInertiaIndexAction', $content);
        $this->assertStringContainsString('HasInertiaStoreAction', $content);
        $this->assertStringNotContainsString('HasInertiaCreateAction', $content);
        $this->assertStringNotContainsString('HasInertiaDestroyAction', $content);
    }

    public function test_generate_controller_with_only_index(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasInertiaIndexAction', $content);
    }

    public function test_generate_controller_has_definition_dependency(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('PostCrudDefinition', $content);
    }

    public function test_generate_controller_has_core_traits(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('ResolveCrudDefinition', $content);
        $this->assertStringContainsString('HasCrudHelpers', $content);
    }

    public function test_generate_controller_has_crud_definition_attribute(): void
    {
        $this->generator->generate('Post', 'inertia', ['index']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('#[CrudDefinition(crudDefinitionClass: PostCrudDefinition::class)]', $content);
    }

    public function test_generate_controller_api_all_methods(): void
    {
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

        $this->generator->generate('Post', 'api', $methods);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasApiIndexAction', $content);
        $this->assertStringContainsString('HasApiCreateAction', $content);
        $this->assertStringContainsString('HasApiStoreAction', $content);
        $this->assertStringContainsString('HasApiShowAction', $content);
        $this->assertStringContainsString('HasApiEditAction', $content);
        $this->assertStringContainsString('HasApiUpdateAction', $content);
        $this->assertStringContainsString('HasApiDestroyAction', $content);
    }

    public function test_generate_controller_with_media_fields(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'label' => 'Title'],
            ['name' => 'avatar', 'type' => 'image', 'label' => 'Avatar', 'modifiers' => ['collection' => 'avatars']],
        ];

        $this->generator->generate('Post', 'inertia', ['index', 'store', 'update'], $fields);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringContainsString('HasMediaActions', $content);
        $this->assertStringContainsString('handleMediaUpload', $content);
    }

    public function test_generate_controller_without_media_no_media_trait(): void
    {
        $this->generator->generate('Post', 'inertia', ['index', 'store']);

        $content = file_get_contents($this->controllerDir.'/PostController.php');
        $this->assertStringNotContainsString('HasMediaActions', $content);
    }

    public function test_generate_skips_if_controller_exists(): void
    {
        file_put_contents($this->controllerDir.'/PostController.php', '<?php // existing');

        $this->generator->generate('Post', 'inertia', ['index']);

        $this->assertSame('<?php // existing', file_get_contents($this->controllerDir.'/PostController.php'));
    }
}
