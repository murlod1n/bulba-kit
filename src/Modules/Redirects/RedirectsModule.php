<?php

namespace Nktlksvch\BulbaKit\Modules\Redirects;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;

class RedirectsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Redirects';
    }

    public function description(): string
    {
        return 'URL redirects management (301/302)';
    }

    public function icon(): string
    {
        return 'arrow-right-left';
    }

    public function modelName(): string
    {
        return 'Redirect';
    }

    public function tableName(): string
    {
        return 'redirects';
    }

    public function fields(): array
    {
        return [
            ['name' => 'url_from', 'type' => 'string', 'modifiers' => ['length' => 2048, 'unique' => true]],
            ['name' => 'url_to', 'type' => 'string', 'modifiers' => ['length' => 2048]],
            ['name' => 'status_code', 'type' => 'integer', 'modifiers' => ['default' => 301]],
            ['name' => 'is_active', 'type' => 'boolean', 'modifiers' => ['default' => true]],
        ];
    }

    public function controllerMethods(): array
    {
        return ['index', 'create', 'store', 'edit', 'update', 'destroy'];
    }

    public function options(): array
    {
        return ['timestamps' => true, 'softDeletes' => false];
    }

    public function navigation(): array
    {
        return [
            ['group' => 'Settings', 'items' => [
                ['title' => 'Redirects', 'href' => '/admin/redirects', 'icon' => 'arrow-right-left'],
            ]],
        ];
    }

    public function seederClass(): ?string
    {
        return null;
    }

    public function seederStub(): ?string
    {
        return null;
    }

    public function customStubs(): array
    {
        return [
            'index-page' => 'redirect-index-page',
        ];
    }

    public function postInstall(object $command): void
    {
        $this->installMiddleware($command);
        $this->registerMiddleware($command);
    }

    protected function installMiddleware(object $command): void
    {
        $stubPath = dirname(__DIR__, 2).'/Modules/Redirects/stubs/redirect-middleware.stub';
        $destination = app_path('Http/Middleware/RedirectMiddleware.php');

        if (File::exists($destination)) {
            return;
        }

        $namespace = 'App\\Http\\Middleware';
        $content = File::get($stubPath);
        $content = str_replace('{{ namespace }}', $namespace, $content);

        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, $content);
    }

    protected function registerMiddleware(object $command): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');
        if (! File::exists($bootstrapPath)) {
            return;
        }

        $content = File::get($bootstrapPath);

        if (str_contains($content, 'RedirectMiddleware')) {
            return;
        }

        // Add middleware alias
        $aliasLine = "        'redirect' => \\App\\Http\\Middleware\\RedirectMiddleware::class,";

        if (str_contains($content, 'withMiddleware')) {
            $content = preg_replace(
                '/(->withMiddleware\(function \(Middleware \$middleware\) \{)/',
                "$1\n\$middleware->alias([\n{$aliasLine}\n    ]);",
                $content,
                1
            );
        }

        // Append middleware to web group
        $appendToWeb = "        \$middleware->appendToGroup('web', \\App\\Http\\Middleware\\RedirectMiddleware::class);";

        if (! str_contains($content, 'RedirectMiddleware::class')) {
            $content = preg_replace(
                '/(->withMiddleware\(function \(Middleware \$middleware\) \{)/',
                "$1\n{$appendToWeb}",
                $content,
                1
            );
        }

        File::put($bootstrapPath, $content);
    }
}
