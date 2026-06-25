<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Generators;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\ResourceGenerator;
use Nktlksvch\BulbaKit\Tests\TestCase;

class ResourceGeneratorTest extends TestCase
{
    private ResourceGenerator $generator;
    private string $resourceDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ResourceGenerator();
        $this->resourceDir = $this->tempDir . '/app/Resources';
        mkdir($this->resourceDir, 0755, true);
        $this->app['config']->set('bulba.resource_namespace', 'App\\Resources');

        // Override app_path to point to temp
        $this->app->useAppPath($this->tempDir . '/app');
    }

    public function test_generate_creates_resource_file(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        $this->generator->generate('Post', $fields, []);

        $this->assertFileExists($this->resourceDir . '/PostResource.php');
    }

    public function test_generate_resource_contains_class_name(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $this->generator->generate('Post', $fields, []);

        $this->assertFileContains($this->resourceDir . '/PostResource.php', 'class PostResource extends AbstractResource');
    }

    public function test_generate_resource_contains_model_reference(): void
    {
        $fields = [];

        $this->generator->generate('Post', $fields, []);

        $this->assertFileContains($this->resourceDir . '/PostResource.php', 'return Post::class');
    }

    public function test_generate_resource_contains_field(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        $this->generator->generate('Post', $fields, []);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'name' => 'title'", $content);
        $this->assertStringContainsString("'type' => 'string'", $content);
        $this->assertStringContainsString("'label' => 'Title'", $content);
    }

    public function test_generate_resource_contains_nullable_field(): void
    {
        $fields = [
            ['name' => 'description', 'type' => 'text', 'modifiers' => ['nullable' => true]],
        ];

        $this->generator->generate('Post', $fields, []);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'nullable' => true", $content);
    }

    public function test_generate_resource_contains_unique_field(): void
    {
        $fields = [
            ['name' => 'slug', 'type' => 'string', 'modifiers' => ['unique' => true]],
        ];

        $this->generator->generate('Post', $fields, []);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'unique' => true", $content);
    }

    public function test_generate_resource_contains_validation_rules(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        $this->generator->generate('Post', $fields, []);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'title'", $content);
        $this->assertStringContainsString("'required'", $content);
        $this->assertStringContainsString("'max:255'", $content);
    }

    public function test_generate_resource_contains_nullable_validation(): void
    {
        $fields = [
            ['name' => 'description', 'type' => 'text', 'modifiers' => ['nullable' => true]],
        ];

        $this->generator->generate('Post', $fields, []);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'description'", $content);
        $this->assertStringContainsString("'nullable'", $content);
    }

    public function test_generate_resource_contains_belongsTo_relation(): void
    {
        $fields = [];

        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
            ],
        ];

        $this->generator->generate('Post', $fields, $relationships);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'category'", $content);
        $this->assertStringContainsString("'type' => 'belongsTo'", $content);
        $this->assertStringContainsString("'foreign_key' => 'category_id'", $content);
    }

    public function test_generate_resource_contains_hasOne_relation(): void
    {
        $fields = [];

        $relationships = [
            [
                'type' => 'hasOne',
                'name' => 'profile',
                'target' => 'Profile',
                'foreign_key' => 'user_id',
                'display_field' => 'bio',
            ],
        ];

        $this->generator->generate('User', $fields, $relationships);

        $content = file_get_contents($this->resourceDir . '/UserResource.php');
        $this->assertStringContainsString("'type' => 'hasOne'", $content);
        $this->assertStringContainsString("'foreign_key' => 'user_id'", $content);
    }

    public function test_generate_resource_contains_hasMany_relation(): void
    {
        $fields = [];

        $relationships = [
            [
                'type' => 'hasMany',
                'name' => 'posts',
                'target' => 'Post',
                'foreign_key' => 'user_id',
                'display_field' => 'title',
            ],
        ];

        $this->generator->generate('User', $fields, $relationships);

        $content = file_get_contents($this->resourceDir . '/UserResource.php');
        $this->assertStringContainsString("'type' => 'hasMany'", $content);
    }

    public function test_generate_resource_contains_belongsToMany_relation(): void
    {
        $fields = [];

        $relationships = [
            [
                'type' => 'belongsToMany',
                'name' => 'tags',
                'target' => 'Tag',
                'display_field' => 'name',
                'pivot_table' => 'post_tag',
            ],
        ];

        $this->generator->generate('Post', $fields, $relationships);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'type' => 'belongsToMany'", $content);
        $this->assertStringContainsString("'pivot_table' => 'post_tag'", $content);
    }

    public function test_generate_resource_contains_fk_field_for_belongsTo(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
            ],
        ];

        $this->generator->generate('Post', $fields, $relationships);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'name' => 'category_id'", $content);
        $this->assertStringContainsString("'type' => 'integer'", $content);
    }

    public function test_generate_resource_contains_exists_validation_for_fk(): void
    {
        $fields = [];

        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
                'nullable' => false,
            ],
        ];

        $this->generator->generate('Post', $fields, $relationships);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString("'category_id'", $content);
        $this->assertStringContainsString("'required'", $content);
        $this->assertStringContainsString("'exists:categories,id'", $content);
    }

    public function test_generate_resource_no_relations_placeholder(): void
    {
        $fields = [];

        $this->generator->generate('Post', $fields, []);

        $content = file_get_contents($this->resourceDir . '/PostResource.php');
        $this->assertStringContainsString('// no relations', $content);
    }

    public function test_generate_resource_with_multiple_fields(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'body', 'type' => 'text', 'modifiers' => ['nullable' => true]],
            ['name' => 'is_active', 'type' => 'boolean', 'modifiers' => []],
            ['name' => 'price', 'type' => 'decimal', 'modifiers' => ['precision' => 8, 'scale' => 2]],
        ];

        $this->generator->generate('Product', $fields, []);

        $content = file_get_contents($this->resourceDir . '/ProductResource.php');
        $this->assertStringContainsString("'title'", $content);
        $this->assertStringContainsString("'body'", $content);
        $this->assertStringContainsString("'is_active'", $content);
        $this->assertStringContainsString("'price'", $content);
    }

    public function test_add_inverse_relation_injects_hasMany_into_existing_resource(): void
    {
        $existingResource = <<<'PHP'
<?php

namespace App\Resources;

use Nktlksvch\BulbaKit\AbstractResource;
use App\Models\User;

class UserResource extends AbstractResource
{
    public static function model(): string
    {
        return User::class;
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'type' => 'string', 'label' => 'Name'],
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => 'required|max:255',
        ];
    }

    public static function relations(): array
    {
        return [
            // no relations
        ];
    }
}
PHP;

        File::put($this->resourceDir . '/UserResource.php', $existingResource);

        $this->generator->addInverseRelation('User', 'hasMany', 'Post', 'user_id', 'title');

        $content = file_get_contents($this->resourceDir . '/UserResource.php');
        $this->assertStringContainsString("'posts'", $content);
        $this->assertStringContainsString("'type' => 'hasMany'", $content);
        $this->assertStringContainsString("'display_field' => 'title'", $content);
    }

    public function test_add_inverse_relation_injects_belongsTo_fk_field(): void
    {
        $existingResource = <<<'PHP'
<?php

namespace App\Resources;

use Nktlksvch\BulbaKit\AbstractResource;
use App\Models\Category;

class CategoryResource extends AbstractResource
{
    public static function model(): string
    {
        return Category::class;
    }

    public static function fields(): array
    {
        return [
            ['name' => 'name', 'type' => 'string', 'label' => 'Name'],
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => 'required|max:255',
        ];
    }

    public static function relations(): array
    {
        return [
            // no relations
        ];
    }
}
PHP;

        File::put($this->resourceDir . '/CategoryResource.php', $existingResource);

        $this->generator->addInverseRelation('Category', 'hasMany', 'Post', 'category_id', 'title');

        $content = file_get_contents($this->resourceDir . '/CategoryResource.php');
        $this->assertStringContainsString("'posts'", $content);
        $this->assertStringContainsString("'type' => 'hasMany'", $content);
    }

    public function test_add_inverse_relation_skips_if_resource_not_exists(): void
    {
        $this->generator->addInverseRelation('NonExistent', 'hasMany', 'Post', 'user_id', 'title');

        $this->assertFileDoesNotExist($this->resourceDir . '/NonExistentResource.php');
    }

    public function test_add_inverse_relation_skips_if_relation_already_exists(): void
    {
        $existingResource = <<<'PHP'
<?php

namespace App\Resources;

use Nktlksvch\BulbaKit\AbstractResource;
use App\Models\User;

class UserResource extends AbstractResource
{
    public static function model(): string
    {
        return User::class;
    }

    public static function fields(): array
    {
        return [];
    }

    public static function validationRules(): array
    {
        return [];
    }

    public static function relations(): array
    {
        return [
            'posts' => ['type' => 'hasMany', 'model' => \App\Models\Post::class],
        ];
    }
}
PHP;

        File::put($this->resourceDir . '/UserResource.php', $existingResource);

        $this->generator->addInverseRelation('User', 'hasMany', 'Post', 'user_id', 'title');

        $content = file_get_contents($this->resourceDir . '/UserResource.php');
        $this->assertStringContainsString("'posts'", $content);
    }
}
