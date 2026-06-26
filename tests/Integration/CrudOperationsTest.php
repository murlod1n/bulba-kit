<?php

namespace Nktlksvch\BulbaKit\Tests\Integration;

use Illuminate\Support\Facades\Schema;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Tests\TestCase;

class CrudOperationsTest extends TestCase
{
    private MigrationGenerator $migrationGen;

    private string $migrationDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrationGen = new MigrationGenerator;
        $this->migrationDir = $this->tempDir.'/database/migrations';
        mkdir($this->migrationDir, 0755, true);
        $this->app->useDatabasePath($this->tempDir.'/database');

        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function runMigration(string $migrationFile): void
    {
        $migration = require $migrationFile;
        $migration->up();
    }

    // -------------------------------------------------------
    // Basic CRUD with simple fields
    // -------------------------------------------------------

    public function test_migration_creates_table_with_basic_fields(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'body', 'type' => 'text', 'modifiers' => ['nullable' => true]],
            ['name' => 'views', 'type' => 'integer', 'modifiers' => []],
            ['name' => 'is_active', 'type' => 'boolean', 'modifiers' => []],
            ['name' => 'price', 'type' => 'decimal', 'modifiers' => ['precision' => 8, 'scale' => 2]],
        ];

        $this->migrationGen->generate('Product', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_products_table.php');
        $this->assertCount(1, $files);

        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasColumn('products', 'title'));
        $this->assertTrue(Schema::hasColumn('products', 'body'));
        $this->assertTrue(Schema::hasColumn('products', 'views'));
        $this->assertTrue(Schema::hasColumn('products', 'is_active'));
        $this->assertTrue(Schema::hasColumn('products', 'price'));
        $this->assertTrue(Schema::hasColumn('products', 'created_at'));
        $this->assertTrue(Schema::hasColumn('products', 'updated_at'));
    }

    public function test_migration_creates_table_without_timestamps(): void
    {
        $fields = [
            ['name' => 'name', 'type' => 'string', 'modifiers' => []],
        ];

        $this->migrationGen->generate('Setting', $fields, [], false, false, []);

        $files = glob($this->migrationDir.'/*_create_settings_table.php');
        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertFalse(Schema::hasColumn('settings', 'created_at'));
    }

    public function test_migration_creates_table_with_soft_deletes(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $this->migrationGen->generate('Post', $fields, [], true, true, []);

        $files = glob($this->migrationDir.'/*_create_posts_table.php');
        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasColumn('posts', 'deleted_at'));
    }

    // -------------------------------------------------------
    // Foreign key constraints
    // -------------------------------------------------------

    public function test_migration_creates_belongs_to_foreign_key(): void
    {
        // Create categories table first
        $catFields = [['name' => 'name', 'type' => 'string', 'modifiers' => []]];
        $this->migrationGen->generate('Category', $catFields, [], true, false, []);
        $catFiles = glob($this->migrationDir.'/*_create_categories_table.php');
        $this->runMigration($catFiles[0]);

        // Create posts table with FK
        $postFields = [['name' => 'title', 'type' => 'string', 'modifiers' => []]];
        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
            ],
        ];

        $this->migrationGen->generate('Post', $postFields, [], true, false, $relationships);
        $postFiles = glob($this->migrationDir.'/*_create_posts_table.php');
        $this->runMigration($postFiles[0]);

        $this->assertTrue(Schema::hasTable('posts'));
        $this->assertTrue(Schema::hasColumn('posts', 'category_id'));
    }

    public function test_migration_creates_nullable_belongs_to_foreign_key(): void
    {
        $catFields = [['name' => 'name', 'type' => 'string', 'modifiers' => []]];
        $this->migrationGen->generate('Category', $catFields, [], true, false, []);
        $catFiles = glob($this->migrationDir.'/*_create_categories_table.php');
        $this->runMigration($catFiles[0]);

        $postFields = [['name' => 'title', 'type' => 'string', 'modifiers' => []]];
        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
                'nullable' => true,
            ],
        ];

        $this->migrationGen->generate('Post', $postFields, [], true, false, $relationships);
        $postFiles = glob($this->migrationDir.'/*_create_posts_table.php');
        $this->runMigration($postFiles[0]);

        $this->assertTrue(Schema::hasColumn('posts', 'category_id'));
    }

    // -------------------------------------------------------
    // Pivot tables
    // -------------------------------------------------------

    public function test_migration_creates_pivot_table(): void
    {
        // Create posts table
        $postFields = [['name' => 'title', 'type' => 'string', 'modifiers' => []]];
        $this->migrationGen->generate('Post', $postFields, [], true, false, []);
        $postFiles = glob($this->migrationDir.'/*_create_posts_table.php');
        $this->runMigration($postFiles[0]);

        // Create tags table
        $tagFields = [['name' => 'name', 'type' => 'string', 'modifiers' => []]];
        $this->migrationGen->generate('Tag', $tagFields, [], true, false, []);
        $tagFiles = glob($this->migrationDir.'/*_create_tags_table.php');
        $this->runMigration($tagFiles[0]);

        // Generate pivot migration
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

        $this->migrationGen->generate('Post', $postFields, [], true, false, $relationships);

        $pivotFiles = glob($this->migrationDir.'/*_create_post_tag_table.php');
        $this->assertCount(1, $pivotFiles);
        $this->runMigration($pivotFiles[0]);

        $this->assertTrue(Schema::hasTable('post_tag'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'post_id'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'tag_id'));
    }

    // -------------------------------------------------------
    // All field types
    // -------------------------------------------------------

    public function test_migration_all_field_types(): void
    {
        $fields = [
            ['name' => 'f_string', 'type' => 'string', 'modifiers' => ['length' => 100]],
            ['name' => 'f_text', 'type' => 'text', 'modifiers' => []],
            ['name' => 'f_integer', 'type' => 'integer', 'modifiers' => []],
            ['name' => 'f_boolean', 'type' => 'boolean', 'modifiers' => []],
            ['name' => 'f_decimal', 'type' => 'decimal', 'modifiers' => ['precision' => 10, 'scale' => 2]],
            ['name' => 'f_date', 'type' => 'date', 'modifiers' => []],
            ['name' => 'f_datetime', 'type' => 'datetime', 'modifiers' => []],
            ['name' => 'f_timestamp', 'type' => 'timestamp', 'modifiers' => []],
            ['name' => 'f_json', 'type' => 'json', 'modifiers' => []],
        ];

        $this->migrationGen->generate('AllTypes', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_all_types_table.php');
        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasTable('all_types'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_string'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_text'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_integer'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_boolean'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_decimal'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_date'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_datetime'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_timestamp'));
        $this->assertTrue(Schema::hasColumn('all_types', 'f_json'));
    }

    // -------------------------------------------------------
    // Nullable and unique modifiers
    // -------------------------------------------------------

    public function test_migration_nullable_field(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
            ['name' => 'subtitle', 'type' => 'string', 'modifiers' => ['nullable' => true]],
        ];

        $this->migrationGen->generate('Article', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_articles_table.php');
        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasColumn('articles', 'subtitle'));
    }

    public function test_migration_unique_field(): void
    {
        $fields = [
            ['name' => 'slug', 'type' => 'string', 'modifiers' => ['unique' => true]],
        ];

        $this->migrationGen->generate('Page', $fields, [], true, false, []);

        $files = glob($this->migrationDir.'/*_create_pages_table.php');
        $this->runMigration($files[0]);

        $this->assertTrue(Schema::hasColumn('pages', 'slug'));
    }

    // -------------------------------------------------------
    // Combined: full CRUD scenario with relationships
    // -------------------------------------------------------

    public function test_full_crud_scenario_with_all_relationship_types(): void
    {
        // 1. Create users table
        $userFields = [
            ['name' => 'name', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'email', 'type' => 'string', 'modifiers' => ['length' => 255, 'unique' => true]],
        ];
        $this->migrationGen->generate('User', $userFields, [], true, false, []);
        $userFiles = glob($this->migrationDir.'/*_create_users_table.php');
        $this->runMigration($userFiles[0]);

        // 2. Create categories table
        $catFields = [
            ['name' => 'name', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];
        $this->migrationGen->generate('Category', $catFields, [], true, false, []);
        $catFiles = glob($this->migrationDir.'/*_create_categories_table.php');
        $this->runMigration($catFiles[0]);

        // 3. Create tags table
        $tagFields = [
            ['name' => 'name', 'type' => 'string', 'modifiers' => ['length' => 255]],
        ];
        $this->migrationGen->generate('Tag', $tagFields, [], true, false, []);
        $tagFiles = glob($this->migrationDir.'/*_create_tags_table.php');
        $this->runMigration($tagFiles[0]);

        // 4. Create posts table with belongsTo user and category
        $postFields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'body', 'type' => 'text', 'modifiers' => ['nullable' => true]],
            ['name' => 'is_published', 'type' => 'boolean', 'modifiers' => []],
        ];
        $postRelationships = [
            [
                'type' => 'belongsTo',
                'name' => 'user',
                'target' => 'User',
                'foreign_key' => 'user_id',
                'display_field' => 'name',
            ],
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
                'nullable' => true,
            ],
            [
                'type' => 'belongsToMany',
                'name' => 'tags',
                'target' => 'Tag',
                'display_field' => 'name',
                'pivot_table' => 'post_tag',
                'pivot_tables' => ['posts', 'tags'],
            ],
        ];
        $this->migrationGen->generate('Post', $postFields, [], true, true, $postRelationships);
        $postFiles = glob($this->migrationDir.'/*_create_posts_table.php');
        $this->runMigration($postFiles[0]);

        // 5. Create pivot table
        $pivotFiles = glob($this->migrationDir.'/*_create_post_tag_table.php');
        $this->assertCount(1, $pivotFiles);
        $this->runMigration($pivotFiles[0]);

        // 6. Create comments table with belongsTo post
        $commentFields = [
            ['name' => 'body', 'type' => 'text', 'modifiers' => []],
        ];
        $commentRelationships = [
            [
                'type' => 'belongsTo',
                'name' => 'post',
                'target' => 'Post',
                'foreign_key' => 'post_id',
                'display_field' => 'title',
            ],
        ];
        $this->migrationGen->generate('Comment', $commentFields, [], true, false, $commentRelationships);
        $commentFiles = glob($this->migrationDir.'/*_create_comments_table.php');
        $this->runMigration($commentFiles[0]);

        // Verify all tables exist
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('tags'));
        $this->assertTrue(Schema::hasTable('posts'));
        $this->assertTrue(Schema::hasTable('post_tag'));
        $this->assertTrue(Schema::hasTable('comments'));

        // Verify FK columns
        $this->assertTrue(Schema::hasColumn('posts', 'user_id'));
        $this->assertTrue(Schema::hasColumn('posts', 'category_id'));
        $this->assertTrue(Schema::hasColumn('posts', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('comments', 'post_id'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'post_id'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'tag_id'));
    }
}
