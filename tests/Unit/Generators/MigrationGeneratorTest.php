<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Generators;

use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Tests\TestCase;

class MigrationGeneratorTest extends TestCase
{
    private MigrationGenerator $generator;

    private string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MigrationGenerator;
        $this->migrationDir = $this->tempDir.'/database/migrations';
        mkdir($this->migrationDir, 0755, true);
    }

    public function test_generate_creates_migration_file(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $this->assertCount(1, $files);
    }

    public function test_generate_migration_contains_table_name(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('posts', $content);
    }

    public function test_generate_migration_with_string_field(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 100]],
        ];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString("\$table->string('title', 100)", $content);
    }

    public function test_generate_migration_with_nullable_field(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'description', 'type' => 'text', 'modifiers' => ['nullable' => true]],
        ];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('->nullable()', $content);
    }

    public function test_generate_migration_with_unique_field(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'slug', 'type' => 'string', 'modifiers' => ['unique' => true]],
        ];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('->unique()', $content);
    }

    public function test_generate_migration_with_decimal_field(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'price', 'type' => 'decimal', 'modifiers' => ['precision' => 10, 'scale' => 2]],
        ];

        $this->generator->generate('Product', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_products_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('->total(10)->places(2)', $content);
    }

    public function test_generate_migration_with_timestamps(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('$table->timestamps()', $content);
    }

    public function test_generate_migration_without_timestamps(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [];

        $this->generator->generate('Post', $fields, [], false, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringNotContainsString('$table->timestamps()', $content);
    }

    public function test_generate_migration_with_soft_deletes(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [];

        $this->generator->generate('Post', $fields, [], true, true, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('$table->softDeletes()', $content);
    }

    public function test_generate_migration_with_belongs_to_foreign_key(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

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

        $this->generator->generate('Post', $fields, [], true, false, $relationships);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString("foreignId('category_id')", $content);
        $this->assertStringContainsString("->constrained('categories')", $content);
    }

    public function test_generate_migration_with_cascade_on_delete(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [];

        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'user',
                'target' => 'User',
                'foreign_key' => 'user_id',
                'display_field' => 'name',
                'cascade_on_delete' => true,
            ],
        ];

        $this->generator->generate('Post', $fields, [], true, false, $relationships);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('->cascadeOnDelete()', $content);
    }

    public function test_generate_migration_with_belongs_to_many_creates_pivot_migration(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [];

        $relationships = [
            [
                'type' => 'belongsToMany',
                'name' => 'tags',
                'target' => 'Tag',
                'display_field' => 'name',
                'pivot_table' => 'post_tag',
                'pivot_tables' => ['posts', 'tags'],
            ],
        ];

        $this->generator->generate('Post', $fields, [], true, false, $relationships);

        $files = glob($this->migrationDir.'/*_create_post_tag_table.php');
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);
        $this->assertStringContainsString('post_tag', $content);
        $this->assertStringContainsString("foreignId('post_id')", $content);
        $this->assertStringContainsString("foreignId('tag_id')", $content);
    }

    public function test_generate_migration_with_multiple_field_types(): void
    {
        $this->app->useDatabasePath($this->tempDir.'/database');

        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'body', 'type' => 'text', 'modifiers' => []],
            ['name' => 'views', 'type' => 'integer', 'modifiers' => []],
            ['name' => 'is_published', 'type' => 'boolean', 'modifiers' => []],
            ['name' => 'price', 'type' => 'decimal', 'modifiers' => ['precision' => 8, 'scale' => 2]],
            ['name' => 'published_at', 'type' => 'datetime', 'modifiers' => []],
            ['name' => 'metadata', 'type' => 'json', 'modifiers' => []],
        ];

        $this->generator->generate('Post', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString("\$table->string('title', 255)", $content);
        $this->assertStringContainsString("\$table->text('body')", $content);
        $this->assertStringContainsString("\$table->integer('views')", $content);
        $this->assertStringContainsString("\$table->boolean('is_published')", $content);
        $this->assertStringContainsString('->total(8)->places(2)', $content);
        $this->assertStringContainsString("\$table->datetime('published_at')", $content);
        $this->assertStringContainsString("\$table->json('metadata')", $content);
    }
}
