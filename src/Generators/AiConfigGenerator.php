<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * AI Config Generator
 *
 * Generates AI configuration files for fields that support AI generation.
 * Creates config files with prompt templates, context fields, and generation parameters.
 *
 * @package Nktlksvch\BulbaKit\Generators
 */
class AiConfigGenerator
{
    use LoadsStubs;
    /**
     * Generate AI configuration file for a model.
     *
     * @param  string $name     Model name
     * @param  array  $aiFields AI field configurations from askForAiGeneration()
     * @return void
     */
    public function generate($name, $aiFields): void
    {
        if (empty($aiFields) || !config('bulba.ai_enabled', true)) {
            return;
        }

        $configPath = config_path(config('bulba.ai_config_path', 'admin/ai'));
        File::ensureDirectoryExists($configPath);

        $stub = $this->getStub('ai-config');
        $promptsArray = $this->buildPromptsArray($aiFields);

        $content = str_replace(
            ['{{ model }}', '{{ prompts }}'],
            [$name, $promptsArray],
            $stub
        );

        File::put($configPath . "/{$name}.php", $content);
    }

    /**
     * Build the prompts array PHP code.
     *
     * @param  array $aiFields AI field configurations
     * @return string PHP array code
     */
    protected function buildPromptsArray(array $aiFields): string
    {
        $promptsArray = '';

        foreach ($aiFields as $ai) {
            $context = implode("', '", $ai['context_fields']);
            $promptsArray .= "        '{$ai['field']}' => [\n";
            $promptsArray .= "            'input_fields' => ['{$context}'],\n";
            $promptsArray .= "            'prompt_template' => '" . addslashes($ai['prompt']) . "',\n";
            $promptsArray .= "            'output_field' => '{$ai['field']}',\n";
            $promptsArray .= "            'max_tokens' => 150,\n";
            $promptsArray .= "            'temperature' => 0.7,\n";
            $promptsArray .= "        ],\n";
        }

        return $promptsArray;
    }
}
