<?php

namespace Nktlksvch\BulbaKit\Generators\Builders;

class MediaBuilder
{
    /**
     * Build registerMediaCollections method for image fields.
     *
     * @param  array<int, array<string, mixed>>  $imageFields  Image field definitions
     * @return string Method code or comment
     */
    public function buildMediaCollections(array $imageFields): string
    {
        if (empty($imageFields)) {
            return '    // media collections';
        }

        $lines = [];

        foreach ($imageFields as $field) {
            $collection = $field['modifiers']['collection'] ?? $field['name'];
            $single = $field['modifiers']['single'] ?? true;

            if ($single) {
                $lines[] = "        \$this->addMediaCollection('{$collection}')->singleFile();";
            } else {
                $lines[] = "        \$this->addMediaCollection('{$collection}');";
            }
        }

        $body = implode("\n", $lines);

        return <<<PHP
    public function registerMediaCollections(): void
    {
{$body}
    }
PHP;
    }

    /**
     * Build registerMediaConversions method for image fields.
     *
     * @param  array<int, array<string, mixed>>  $imageFields  Image field definitions
     * @return string Method code or comment
     */
    public function buildMediaConversions(array $imageFields): string
    {
        if (empty($imageFields)) {
            return '    // media conversions';
        }

        $lines = [];

        foreach ($imageFields as $field) {
            $width = $field['modifiers']['thumb_width'] ?? 200;
            $height = $field['modifiers']['thumb_height'] ?? 200;

            $lines[] = "        \$this->addMediaConversion('thumb')";
            $lines[] = "            ->fit(Fit::Contain, {$width}, {$height})";
            $lines[] = '            ->nonQueued();';
            $lines[] = '';
            $lines[] = "        \$this->addMediaConversion('webp')";
            $lines[] = '            ->fit(Fit::Max, 800, 600)';
            $lines[] = "            ->format('webp')";
            $lines[] = '            ->nonQueued();';
        }

        $body = implode("\n", $lines);

        return <<<PHP
    public function registerMediaConversions(?Media \$media = null): void
    {
{$body}
    }
PHP;
    }
}
