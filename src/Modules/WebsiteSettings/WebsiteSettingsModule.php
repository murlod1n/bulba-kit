<?php

namespace Nktlksvch\BulbaKit\Modules\WebsiteSettings;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;

class WebsiteSettingsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Website Settings';
    }

    public function description(): string
    {
        return 'Site configuration (title, description, contacts, SEO)';
    }

    public function icon(): string
    {
        return 'settings';
    }

    public function modelName(): string
    {
        return 'WebsiteSetting';
    }

    public function tableName(): string
    {
        return 'website_settings';
    }

    public function fields(): array
    {
        return [
            ['name' => 'key', 'type' => 'string', 'modifiers' => ['length' => 255, 'unique' => true]],
            ['name' => 'value', 'type' => 'text', 'modifiers' => ['nullable' => true]],
            ['name' => 'type', 'type' => 'string', 'modifiers' => ['length' => 20, 'default' => 'string']],
            ['name' => 'group', 'type' => 'string', 'modifiers' => ['length' => 50, 'default' => 'general']],
            ['name' => 'label', 'type' => 'string', 'modifiers' => ['length' => 255, 'nullable' => true]],
            ['name' => 'sort', 'type' => 'integer', 'modifiers' => ['default' => 0]],
        ];
    }

    public function controllerMethods(): array
    {
        return ['index'];
    }

    public function options(): array
    {
        return ['timestamps' => true, 'softDeletes' => false];
    }

    public function navigation(): array
    {
        return [
            ['group' => 'Settings', 'items' => [
                ['title' => 'Site Settings', 'href' => '/admin/website-settings', 'icon' => 'settings'],
            ]],
        ];
    }

    public function seederClass(): ?string
    {
        return 'WebsiteSettingSeeder';
    }

    public function seederStub(): ?string
    {
        return 'website-setting-seeder';
    }

    public function customStubs(): array
    {
        return [
            'index-page' => 'website-setting-index-page',
            'controller-index' => 'website-setting-index-method',
            'controller-method' => 'website-setting-bulk-update-method',
        ];
    }

    public function postInstall(object $command): void
    {
        // Register seeder in DatabaseSeeder
        $this->registerSeeder($command);
    }

    protected function registerSeeder(object $command): void
    {
        $seederPath = database_path('seeders/DatabaseSeeder.php');
        if (! File::exists($seederPath)) {
            return;
        }

        $content = File::get($seederPath);

        if (str_contains($content, 'WebsiteSettingSeeder')) {
            return;
        }

        $useStatement = 'use Database\\Seeders\\WebsiteSettingSeeder;';
        $callLine = '        $this->call(WebsiteSettingSeeder::class);';

        // Add use statement
        if (! str_contains($content, $useStatement)) {
            $content = preg_replace(
                '/(use Illuminate\\\\Support\\\\Facades\\\\Schema;)/',
                "$1\n{$useStatement}",
                $content,
                1
            );
        }

        // Add call inside run()
        if (str_contains($content, 'public function run()')) {
            $content = preg_replace(
                '/(public function run\(\): void\s*\n\s*\{)/',
                "$1\n{$callLine}",
                $content,
                1
            );
        }

        File::put($seederPath, $content);
    }
}
