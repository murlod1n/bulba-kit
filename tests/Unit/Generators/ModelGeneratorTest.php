<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Generators;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Tests\TestCase;

class ModelGeneratorTest extends TestCase
{
    private ModelGenerator $generator;

    private string $modelsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ModelGenerator;
        $this->modelsDir = $this->tempDir.'/app/Models';
        mkdir($this->modelsDir, 0755, true);
        $this->app->useAppPath($this->tempDir.'/app');
    }

    public function test_generate_creates_model_file(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $this->generator->generate('Post', $fields, false, []);

        $this->assertFileExists($this->modelsDir.'/Post.php');
    }

    public function test_generate_model_contains_class_name(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $this->generator->generate('Post', $fields, false, []);

        $this->assertFileContains($this->modelsDir.'/Post.php', 'class Post extends Model');
    }

    public function test_generate_model_contains_fillable(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
            ['name' => 'body', 'type' => 'text', 'modifiers' => []],
        ];

        $this->generator->generate('Post', $fields, false, []);

        $content = file_get_contents($this->modelsDir.'/Post.php');
        $this->assertStringContainsString("'title', 'body'", $content);
    }

    public function test_generate_model_without_soft_deletes(): void
    {
        $fields = [];

        $this->generator->generate('Post', $fields, false, []);

        $this->assertFileNotContains($this->modelsDir.'/Post.php', 'SoftDeletes');
    }

    public function test_generate_model_with_soft_deletes(): void
    {
        $fields = [];

        $this->generator->generate('Post', $fields, true, []);

        $this->assertFileContains($this->modelsDir.'/Post.php', 'use SoftDeletes');
        $this->assertFileContains($this->modelsDir.'/Post.php', 'use Illuminate\\Database\\Eloquent\\SoftDeletes');
    }

    public function test_generate_model_with_belongs_to_relationship(): void
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

        $this->generator->generate('Post', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/Post.php');
        $this->assertStringContainsString('public function category()', $content);
        $this->assertStringContainsString('belongsTo(Category::class', $content);
        $this->assertStringContainsString("'category_id'", $content);
        $this->assertStringContainsString('use App\\Models\\Category;', $content);
    }

    public function test_generate_model_with_has_one_relationship(): void
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

        $this->generator->generate('User', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/User.php');
        $this->assertStringContainsString('public function profile()', $content);
        $this->assertStringContainsString('hasOne(Profile::class', $content);
        $this->assertStringContainsString("'user_id'", $content);
    }

    public function test_generate_model_with_has_many_relationship(): void
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

        $this->generator->generate('User', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/User.php');
        $this->assertStringContainsString('public function posts()', $content);
        $this->assertStringContainsString('hasMany(Post::class', $content);
    }

    public function test_generate_model_with_belongs_to_many_relationship(): void
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

        $this->generator->generate('Post', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/Post.php');
        $this->assertStringContainsString('public function tags()', $content);
        $this->assertStringContainsString('belongsToMany(Tag::class', $content);
        $this->assertStringContainsString("'post_tag'", $content);
    }

    public function test_generate_model_with_multiple_relationships(): void
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
            [
                'type' => 'hasMany',
                'name' => 'comments',
                'target' => 'Comment',
                'foreign_key' => 'post_id',
                'display_field' => 'body',
            ],
            [
                'type' => 'belongsToMany',
                'name' => 'tags',
                'target' => 'Tag',
                'display_field' => 'name',
                'pivot_table' => 'post_tag',
            ],
        ];

        $this->generator->generate('Post', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/Post.php');
        $this->assertStringContainsString('public function category()', $content);
        $this->assertStringContainsString('public function comments()', $content);
        $this->assertStringContainsString('public function tags()', $content);
    }

    public function test_generate_skips_if_model_exists(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        File::put($this->modelsDir.'/Post.php', '<?php // existing');

        $this->generator->generate('Post', $fields, false, []);

        $this->assertSame('<?php // existing', file_get_contents($this->modelsDir.'/Post.php'));
    }

    public function test_add_inverse_relation_injects_has_many_into_existing_model(): void
    {
        $existingModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name'];
}
PHP;

        File::put($this->modelsDir.'/User.php', $existingModel);

        $this->generator->addInverseRelation('User', 'hasMany', 'Post', 'user_id');

        $content = file_get_contents($this->modelsDir.'/User.php');
        $this->assertStringContainsString('public function posts()', $content);
        $this->assertStringContainsString('hasMany(Post::class', $content);
        $this->assertStringContainsString('use App\\Models\\Post;', $content);
    }

    public function test_add_inverse_belongs_to_adds_fk_to_fillable(): void
    {
        $existingModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Engine extends Model
{
    protected $fillable = ['name', 'power'];
}
PHP;

        File::put($this->modelsDir.'/Engine.php', $existingModel);

        $this->generator->addInverseRelation('Engine', 'belongsTo', 'Car', 'car_id');

        $content = file_get_contents($this->modelsDir.'/Engine.php');
        $this->assertStringContainsString('public function car()', $content);
        $this->assertStringContainsString('belongsTo(Car::class', $content);
        $this->assertStringContainsString("'car_id'", $content);
        $this->assertStringContainsString("'name', 'power', 'car_id'", $content);
    }

    public function test_add_inverse_belongs_to_does_not_duplicate_fk_in_fillable(): void
    {
        $existingModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Engine extends Model
{
    protected $fillable = ['name', 'car_id'];
}
PHP;

        File::put($this->modelsDir.'/Engine.php', $existingModel);

        $this->generator->addInverseRelation('Engine', 'belongsTo', 'Car', 'car_id');

        $content = file_get_contents($this->modelsDir.'/Engine.php');
        $this->assertStringContainsString('public function car()', $content);
        // car_id should appear only once in fillable
        $this->assertStringNotContainsString("'car_id', 'car_id'", $content);
    }

    public function test_add_inverse_relation_injects_belongs_to_many_into_existing_model(): void
    {
        $existingModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['name'];
}
PHP;

        File::put($this->modelsDir.'/Tag.php', $existingModel);

        $this->generator->addInverseRelation('Tag', 'belongsToMany', 'Post', 'post_tag');

        $content = file_get_contents($this->modelsDir.'/Tag.php');
        $this->assertStringContainsString('public function posts()', $content);
        $this->assertStringContainsString('belongsToMany(Post::class', $content);
        $this->assertStringContainsString("'post_tag'", $content);
    }

    public function test_add_inverse_relation_skips_if_method_exists(): void
    {
        $existingModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Post;

class User extends Model
{
    protected $fillable = ['name'];

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
PHP;

        File::put($this->modelsDir.'/User.php', $existingModel);

        $this->generator->addInverseRelation('User', 'hasMany', 'Post', 'user_id');

        $content = file_get_contents($this->modelsDir.'/User.php');
        $this->assertStringContainsString('public function posts()', $content);
    }

    public function test_add_inverse_relation_skips_if_file_not_exists(): void
    {
        $this->generator->addInverseRelation('NonExistent', 'hasMany', 'Post', 'user_id');

        $this->assertFileDoesNotExist($this->modelsDir.'/NonExistent.php');
    }

    public function test_generate_model_belongs_to_many_method_name_is_plural(): void
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

        $this->generator->generate('Post', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/Post.php');
        $this->assertStringContainsString('public function tags()', $content);
    }

    public function test_generate_model_has_many_method_name_is_plural(): void
    {
        $fields = [];

        $relationships = [
            [
                'type' => 'hasMany',
                'name' => 'comments',
                'target' => 'Comment',
                'foreign_key' => 'post_id',
                'display_field' => 'body',
            ],
        ];

        $this->generator->generate('Post', $fields, false, $relationships);

        $content = file_get_contents($this->modelsDir.'/Post.php');
        $this->assertStringContainsString('public function comments()', $content);
    }
}
