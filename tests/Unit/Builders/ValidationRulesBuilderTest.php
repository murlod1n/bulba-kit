<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Builders;

use PHPUnit\Framework\TestCase;
use Nktlksvch\BulbaKit\Generators\Builders\ValidationRulesBuilder;

class ValidationRulesBuilderTest extends TestCase
{
    private ValidationRulesBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ValidationRulesBuilder();
    }

    public function test_build_required_field(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('required', $result['title']);
    }

    public function test_build_nullable_field(): void
    {
        $fields = [
            ['name' => 'description', 'type' => 'text', 'modifiers' => ['nullable' => true]],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('nullable', $result['description']);
        $this->assertNotContains('required', $result['description']);
    }

    public function test_build_string_with_length(): void
    {
        $fields = [
            ['name' => 'title', 'type' => 'string', 'modifiers' => ['length' => 100]],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('max:100', $result['title']);
    }

    public function test_build_unique_field(): void
    {
        $fields = [
            ['name' => 'slug', 'type' => 'string', 'modifiers' => ['unique' => true]],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('unique:posts,slug', $result['slug']);
    }

    public function test_build_integer_field(): void
    {
        $fields = [
            ['name' => 'count', 'type' => 'integer', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('integer', $result['count']);
    }

    public function test_build_boolean_field(): void
    {
        $fields = [
            ['name' => 'is_active', 'type' => 'boolean', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('boolean', $result['is_active']);
    }

    public function test_build_decimal_field(): void
    {
        $fields = [
            ['name' => 'price', 'type' => 'decimal', 'modifiers' => []],
        ];

        $result = $this->builder->build($fields, 'Post');

        $this->assertContains('numeric', $result['price']);
    }

    public function test_build_belongsTo_fk_field(): void
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

        $result = $this->builder->build($fields, 'Post', $relationships);

        $this->assertArrayHasKey('category_id', $result);
        $this->assertContains('required', $result['category_id']);
        $this->assertContains('integer', $result['category_id']);
        $this->assertContains('exists:categories,id', $result['category_id']);
    }

    public function test_build_nullable_belongsTo_fk_field(): void
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

        $result = $this->builder->build($fields, 'Post', $relationships);

        $this->assertContains('nullable', $result['category_id']);
        $this->assertNotContains('required', $result['category_id']);
    }

    public function test_build_does_not_duplicate_fk_rule_when_field_exists(): void
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

        $result = $this->builder->build($fields, 'Post', $relationships);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('category_id', $result);
    }

    public function test_build_unique_uses_snake_case_plural_model_name(): void
    {
        $fields = [
            ['name' => 'email', 'type' => 'string', 'modifiers' => ['unique' => true]],
        ];

        $result = $this->builder->build($fields, 'UserCategory');

        $this->assertContains('unique:usercategories,email', $result['email']);
    }
}
