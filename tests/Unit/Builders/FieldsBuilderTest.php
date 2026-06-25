<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Builders;

use PHPUnit\Framework\TestCase;
use Nktlksvch\BulbaKit\Generators\Builders\FieldsBuilder;

class FieldsBuilderTest extends TestCase
{
    private FieldsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new FieldsBuilder();
    }

    public function test_build_basic_fields(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'body', 'type' => 'text', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, []);

        $this->assertCount(2, $result);
        $this->assertSame('title', $result[0]['name']);
        $this->assertSame('string', $result[0]['type']);
        $this->assertSame('Title', $result[0]['label']);
        $this->assertSame('body', $result[1]['name']);
        $this->assertSame('text', $result[1]['type']);
    }

    public function test_build_field_with_nullable_modifier(): void
    {
        $fields = [
            ['name' => 'description', 'type' => 'text', 'modifiers' => ['nullable' => true]],
        ];

        $result = $this->builder->build($fields, []);

        $this->assertTrue($result[0]['nullable']);
    }

    public function test_build_field_with_unique_modifier(): void
    {
        $fields = [
            ['name' => 'email', 'type' => 'string', 'modifiers' => ['unique' => true]],
        ];

        $result = $this->builder->build($fields, []);

        $this->assertTrue($result[0]['unique']);
    }

    public function test_build_field_without_modifiers(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, []);

        $this->assertArrayNotHasKey('nullable', $result[0]);
        $this->assertArrayNotHasKey('unique', $result[0]);
    }

    public function test_build_adds_fk_field_for_belongsTo_relationship(): void
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

        $result = $this->builder->build($fields, $relationships);

        $this->assertCount(2, $result);
        $this->assertSame('category_id', $result[1]['name']);
        $this->assertSame('integer', $result[1]['type']);
        $this->assertSame('Category Id', $result[1]['label']);
    }

    public function test_build_does_not_duplicate_fk_field_if_already_in_fields(): void
    {
        $fields = [
            ['name' => 'category_id', 'type' => 'integer', 'modifiers' => []],
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

        $result = $this->builder->build($fields, $relationships);

        $this->assertCount(1, $result);
    }

    public function test_build_fk_field_respects_nullable_from_relationship(): void
    {
        $fields = [];

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

        $result = $this->builder->build($fields, $relationships);

        $this->assertTrue($result[0]['nullable']);
    }

    public function test_build_ignores_non_belongsTo_relationships_for_fk_fields(): void
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

        $result = $this->builder->build($fields, $relationships);

        $this->assertEmpty($result);
    }

    public function test_build_label_generation_from_snake_case(): void
    {
        $fields = [
            ['name' => 'first_name', 'type' => 'string', 'modifiers' => []],
            ['name' => 'created_at', 'type' => 'timestamp', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, []);

        $this->assertSame('First Name', $result[0]['label']);
        $this->assertSame('Created At', $result[1]['label']);
    }

    public function test_image_field_generates_virtual_fields(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 255]],
            ['name' => 'image', 'type' => 'image', 'modifiers' => ['collection' => 'image', 'thumb_width' => 200, 'thumb_height' => 200, 'single' => true]],
        ];

        $result = $this->builder->build($fields, []);

        // Should have: title + image_url + image_thumb + image_alt = 4 fields
        $this->assertCount(4, $result);

        $names = array_column($result, 'name');
        $this->assertContains('title', $names);
        $this->assertContains('image_url', $names);
        $this->assertContains('image_thumb', $names);
        $this->assertContains('image_alt', $names);
    }

    public function test_image_field_url_is_read_only(): void
    {
        $fields = [
            ['name' => 'photo', 'type' => 'image', 'modifiers' => ['collection' => 'photo', 'thumb_width' => 100, 'thumb_height' => 100, 'single' => true]],
        ];

        $result = $this->builder->build($fields, []);

        $urlField = collect($result)->firstWhere('name', 'photo_url');
        $this->assertNotNull($urlField);
        $this->assertTrue($urlField['readOnly']);

        $thumbField = collect($result)->firstWhere('name', 'photo_thumb');
        $this->assertNotNull($thumbField);
        $this->assertTrue($thumbField['readOnly']);
    }

    public function test_image_field_alt_is_nullable(): void
    {
        $fields = [
            ['name' => 'photo', 'type' => 'image', 'modifiers' => ['collection' => 'photo', 'thumb_width' => 100, 'thumb_height' => 100, 'single' => true]],
        ];

        $result = $this->builder->build($fields, []);

        $altField = collect($result)->firstWhere('name', 'photo_alt');
        $this->assertNotNull($altField);
        $this->assertTrue($altField['nullable']);
    }

    public function test_image_field_not_present_as_original_field(): void
    {
        $fields = [
            ['name' => 'photo', 'type' => 'image', 'modifiers' => ['collection' => 'photo', 'thumb_width' => 100, 'thumb_height' => 100, 'single' => true]],
        ];

        $result = $this->builder->build($fields, []);

        $names = array_column($result, 'name');
        $this->assertNotContains('photo', $names);
    }
}
