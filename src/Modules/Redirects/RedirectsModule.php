<?php

namespace Nktlksvch\BulbaKit\Modules\Redirects;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\CrudDefinitionGenerator;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Generators\ReactPageGenerator;
use Nktlksvch\BulbaKit\Generators\RouteGenerator;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;

/**
 * Redirects module — URL redirect management with middleware.
 *
 * Unlike a DefaultCrud resource, this module installs custom infrastructure:
 * - Standard CRUD (migration, model, controller, pages, routes)
 * - RedirectMiddleware that intercepts requests and performs redirects
 * - Cache-based redirect lookup (1 hour TTL)
 * - Middleware registration in bootstrap/app.php
 *
 * Model: Redirect
 * Table: redirects
 * Route: /admin/redirects
 */
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

    /**
     * Install the redirects module: migration, model, resource, controller,
     * custom index page, routes, and redirect middleware.
     */
    public function install(object $command): void
    {
        $command->info('  Generating migration...');
        app(MigrationGenerator::class)->generate(
            'Redirect',
            $this->fields(),
            [],
            true,
            false,
            []
        );

        $command->info('  Generating model...');
        app(ModelGenerator::class)->generate(
            'Redirect',
            $this->fields(),
            false,
            []
        );

        $command->info('  Generating resource...');
        app(CrudDefinitionGenerator::class)->generate(
            'Redirect',
            $this->fields(),
            []
        );

        $command->info('  Generating controller...');
        app(ControllerGenerator::class)->generate(
            'Redirect',
            'inertia',
            $this->controllerMethods(),
            $this->fields()
        );

        $command->info('  Generating pages...');
        $this->installPages($command);

        $command->info('  Generating routes...');
        app(RouteGenerator::class)->generate(
            'Redirect',
            'inertia',
            $this->controllerMethods()
        );

        $command->info('  Installing middleware...');
        $this->installMiddleware();
        $this->registerMiddleware();
    }

    /**
     * @return array<int, array{group: string, items: array<int, array{title: string, href: string, icon: string}>}>
     */
    public function navigation(): array
    {
        return [
            ['group' => 'Settings', 'items' => [
                ['title' => 'Redirects', 'href' => '/admin/redirects', 'icon' => 'arrow-right-left'],
            ]],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fields(): array
    {
        return [
            ['name' => 'url_from', 'type' => 'string', 'modifiers' => ['length' => 2048, 'unique' => true]],
            ['name' => 'url_to', 'type' => 'string', 'modifiers' => ['length' => 2048]],
            ['name' => 'status_code', 'type' => 'integer', 'modifiers' => ['default' => 301]],
            ['name' => 'is_active', 'type' => 'boolean', 'modifiers' => ['default' => true]],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function controllerMethods(): array
    {
        return ['index', 'create', 'store', 'edit', 'update', 'destroy'];
    }

    /**
     * Install the custom index page from stub, or fall back to ReactPageGenerator.
     */
    protected function installPages(object $command): void
    {
        $pagesPath = config('bulba.react_pages_path', 'admin');
        $pagesDir = resource_path("js/pages/{$pagesPath}/Redirect");

        File::ensureDirectoryExists($pagesDir);

        $stubPath = dirname(__DIR__).'/Redirects/stubs/redirect-index-page.stub';

        if (File::exists($stubPath)) {
            File::put($pagesDir.'/Index.tsx', File::get($stubPath));
            $command->info('    Created: Index.tsx');
        } else {
            app(ReactPageGenerator::class)->generate(
                'Redirect',
                $this->fields(),
                []
            );
        }
    }

    /**
     * Copy RedirectMiddleware stub to the host app's Http/Middleware directory.
     */
    protected function installMiddleware(): void
    {
        $stubPath = dirname(__DIR__).'/Redirects/stubs/redirect-middleware.stub';
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

    /**
     * Register RedirectMiddleware in bootstrap/app.php: add alias and append to web group.
     */
    protected function registerMiddleware(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');
        if (! File::exists($bootstrapPath)) {
            return;
        }

        $content = File::get($bootstrapPath);

        if (str_contains($content, 'RedirectMiddleware')) {
            return;
        }

        $aliasLine = "        'redirect' => \\App\\Http\\Middleware\\RedirectMiddleware::class,";

        if (str_contains($content, 'withMiddleware')) {
            $content = preg_replace(
                '/(->withMiddleware\(function \(Middleware \$middleware\) \{)/',
                "$1\n\$middleware->alias([\n{$aliasLine}\n    ]);",
                $content,
                1
            );
        }

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
