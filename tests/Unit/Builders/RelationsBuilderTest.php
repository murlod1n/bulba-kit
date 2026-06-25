<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Builders;

use PHPUnit\Framework\TestCase;
use Nktlksvch\BulbaKit\Generators\Builders\RelationsBuilder;
use Nktlksvch\BulbaKit\Generators\Builders\ArrayRenderer;

class RelationsBuilderTest extends TestCase
{
    private RelationsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new RelationsBuilder();
    }

    public function test_build_empty_relationships(): void
    {
        $result = $this->builder->build([]);

        $this->assertEmpty($result);
    }

    public function test_build_belongsTo_relationship(): void
    {
        $relationships = [
            [
                'type' => 'belongsTo',
                'name' => 'category',
                'target' => 'Category',
                'foreign_key' => 'category_id',
                'display_field' => 'name',
            ],
        ];

        $result = $this->builder->build($relationships);

        $this->assertArrayHasKey('category', $result);
        $this->assertSame('belongsTo', $result['category']['type']);
        $this->assertSame(ArrayRenderer::EXPRESSION_PREFIX . '\\App\\Models\\Category::class', $result['category']['model']);
        $this->assertSame('category_id', $result['category']['foreign_key']);
        $this->assertSame('name', $result['category']['display_field']);
    }

    public function test_build_hasOne_relationship(): void
    {
        $relationships = [
            [
                'type' => 'hasOne',
                'name' => 'profile',
                'target' => 'Profile',
                'foreign_key' => 'user_id',
                'display_field' => 'bio',
            ],
        ];

        $result = $this->builder->build($relationships);

        $this->assertArrayHasKey('profile', $result);
        $this->assertSame('hasOne', $result['profile']['type']);
        $this->assertSame(ArrayRenderer::EXPRESSION_PREFIX . '\\App\\Models\\Profile::class', $result['profile']['model']);
        $this->assertSame('user_id', $result['profile']['foreign_key']);
    }

    public function test_build_hasMany_relationship(): void
    {
        $relationships = [
            [
                'type' => 'hasMany',
                'name' => 'posts',
                'target' => 'Post',
                'foreign_key' => 'user_id',
                'display_field' => 'title',
            ],
        ];

        $result = $this->builder->build($relationships);

        $this->assertArrayHasKey('posts', $result);
        $this->assertSame('hasMany', $result['posts']['type']);
        $this->assertArrayNotHasKey('foreign_key', $result['posts']);
    }

    public function test_build_belongsToMany_relationship(): void
    {
        $relationships = [
            [
                'type' => 'belongsToMany',
                'name' => 'tags',
                'target' => 'Tag',
                'display_field' => 'name',
                'pivot_table' => 'post_tag',
            ],
        ];

        $result = $this->builder->build($relationships);

        $this->assertArrayHasKey('tags', $result);
        $this->assertSame('belongsToMany', $result['tags']['type']);
        $this->assertSame('post_tag', $result['tags']['pivot_table']);
        $this->assertArrayNotHasKey('foreign_key', $result['tags']);
    }

    public function test_build_multiple_relationships(): void
    {
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

        $result = $this->builder->build($relationships);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('comments', $result);
        $this->assertArrayHasKey('tags', $result);
    }

    public function test_build_hasOne_has_foreign_key_in_output(): void
    {
        $relationships = [
            [
                'type' => 'hasOne',
                'name' => 'profile',
                'target' => 'Profile',
                'foreign_key' => 'user_id',
                'display_field' => 'bio',
            ],
        ];

        $result = $this->builder->build($relationships);

        $this->assertArrayHasKey('foreign_key', $result['profile']);
        $this->assertSame('user_id', $result['profile']['foreign_key']);
    }

    public function test_build_hasMany_has_no_foreign_key_in_output(): void
    {
        $relationships = [
            [
                'type' => 'hasMany',
                'name' => 'posts',
                'target' => 'Post',
                'foreign_key' => 'user_id',
                'display_field' => 'title',
            ],
        ];

        $result = $this->builder->build($relationships);

        $this->assertArrayNotHasKey('foreign_key', $result['posts']);
    }
}
