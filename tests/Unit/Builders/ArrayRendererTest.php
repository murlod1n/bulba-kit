<?php

namespace Nktlksvch\BulbaKit\Tests\Unit\Builders;

use PHPUnit\Framework\TestCase;
use Nktlksvch\BulbaKit\Generators\Builders\ArrayRenderer;

class ArrayRendererTest extends TestCase
{
    private ArrayRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ArrayRenderer();
    }

    public function test_render_simple_key_value_array(): void
    {
        $array = ['name' => 'title', 'type' => 'string', 'label' => 'Title'];
        $result = $this->renderer->render($array, 0);

        $this->assertStringContainsString("'name' => 'title'", $result);
        $this->assertStringContainsString("'type' => 'string'", $result);
        $this->assertStringContainsString("'label' => 'Title'", $result);
    }

    public function test_render_nested_array(): void
    {
        $array = [
            'name' => 'title',
            'modifiers' => ['nullable' => true, 'length' => 255],
        ];
        $result = $this->renderer->render($array, 0);

        $this->assertStringContainsString("'name' => 'title'", $result);
        $this->assertStringContainsString("'modifiers' => ['nullable' => true, 'length' => 255]", $result);
    }

    public function test_render_with_indentation(): void
    {
        $array = ['key' => 'value'];
        $result = $this->renderer->render($array, 3);

        $this->assertStringStartsWith('            ', $result); // 12 spaces = 3 levels
        $this->assertStringContainsString("'key' => 'value',", $result);
    }

    public function test_render_bool_values(): void
    {
        $array = ['active' => true, 'deleted' => false];
        $result = $this->renderer->render($array, 0);

        $this->assertStringContainsString("'active' => true", $result);
        $this->assertStringContainsString("'deleted' => false", $result);
    }

    public function test_render_numeric_values(): void
    {
        $array = ['count' => 42, 'price' => 9.99];
        $result = $this->renderer->render($array, 0);

        $this->assertStringContainsString("'count' => 42", $result);
        $this->assertStringContainsString("'price' => 9.99", $result);
    }

    public function test_render_integer_indexed_array(): void
    {
        $array = ['nullable', 'required', 'max:255'];
        $result = $this->renderer->render($array, 0);

        $this->assertStringContainsString("'nullable'", $result);
        $this->assertStringContainsString("'required'", $result);
        $this->assertStringContainsString("'max:255'", $result);
        $this->assertStringNotContainsString('=>', $result);
    }

    public function test_render_empty_array_returns_empty_string(): void
    {
        $result = $this->renderer->render([], 0);
        $this->assertSame('', $result);
    }

    public function test_render_entries_end_with_comma(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $result = $this->renderer->render($array, 0);

        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            $this->assertStringEndsWith(',', rtrim($line));
        }
    }

    public function test_render_preserves_backslashes_in_strings(): void
    {
        $array = ['model' => '\\App\\Models\\Post::class'];
        $result = $this->renderer->render($array, 0);

        $this->assertStringContainsString("'model' =>", $result);
        $this->assertStringContainsString('Post::class', $result);
    }
}
