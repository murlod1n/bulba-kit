<?php

namespace Nktlksvch\BulbaKit\Generators\Builders;

/**
 * ArrayRenderer
 *
 * Converts PHP arrays into formatted PHP code strings for code generation.
 * Handles nested arrays, scalar values, booleans, and null values with
 * proper quoting and indentation.
 *
 * Used by ResourceGenerator to render fields, validation rules, and relations
 * arrays into valid PHP code for generated Resource class files.
 */
class ArrayRenderer
{
    /**
     * Prefix used to mark values that should be rendered as raw PHP expressions
     * without quoting. Stripped before output.
     */
    public const EXPRESSION_PREFIX = "\0expr:";

    /**
     * Render an associative array as PHP code with indentation.
     *
     * Each key-value pair is rendered on its own line with proper quoting,
     * indentation, and trailing comma. Suitable for multi-line array content
     * inside return [...] blocks.
     *
     * @param  array<int|string, mixed> $array      The array to render
     * @param  int   $indentLevel Number of indentation levels (each level = 4 spaces)
     * @return string PHP code string, or empty string if array is empty
     */
    public function render(array $array, int $indentLevel = 3): string
    {
        if (empty($array)) {
            return '';
        }

        $indent = str_repeat('    ', $indentLevel);
        $lines = [];

        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $lines[] = $indent . $this->renderValue($value) . ',';
            } else {
                $lines[] = $indent . "'{$key}' => " . $this->renderValue($value) . ',';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render a single value as a PHP code string.
     *
     * Handles arrays (recursive, inline format), booleans, null, integers,
     * floats, and strings (with addslashes escaping).
     *
     * @param  mixed $value The value to render
     * @return string PHP code representation of the value
     */
    public function renderValue(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $v) {
                if (is_int($k)) {
                    $parts[] = $this->renderValue($v);
                } else {
                    $parts[] = "'{$k}' => " . $this->renderValue($v);
                }
            }
            return '[' . implode(', ', $parts) . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value) && str_starts_with($value, self::EXPRESSION_PREFIX)) {
            return substr($value, strlen(self::EXPRESSION_PREFIX));
        }

        return "'" . addslashes((string) $value) . "'";
    }

    /**
     * Wrap a PHP expression so it renders without quotes.
     *
     * Use this for values that must be evaluated as PHP code at runtime
     * (e.g., ::class constants) rather than treated as string literals.
     *
     * @param  string $expression The PHP expression (e.g., 'Post::class')
     * @return string Marked expression string
     */
    public function expr(string $expression): string
    {
        return self::EXPRESSION_PREFIX . $expression;
    }

    /**
     * Render a value that should be output as a raw PHP expression.
     *
     * Unlike renderValue(), this does not wrap the value in quotes.
     * Used for ::class constants and other PHP expressions that must
     * be evaluated at runtime rather than treated as string literals.
     *
     * @param  string $value The PHP expression to render
     * @return string Raw PHP expression string
     */
    public function renderExpression(string $value): string
    {
        return $value;
    }
}
