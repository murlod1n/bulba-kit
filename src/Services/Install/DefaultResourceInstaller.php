<?php

namespace Nktlksvch\BulbaKit\Services\Install;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\DefaultCrud\Contracts\DefaultCrud;
use Nktlksvch\BulbaKit\DefaultCrud\DefaultCrudRegistry;
use Nktlksvch\BulbaKit\Generators\ControllerGenerator;
use Nktlksvch\BulbaKit\Generators\CrudDefinitionGenerator;
use Nktlksvch\BulbaKit\Generators\MigrationGenerator;
use Nktlksvch\BulbaKit\Generators\ModelGenerator;
use Nktlksvch\BulbaKit\Generators\ReactPageGenerator;
use Nktlksvch\BulbaKit\Generators\RouteGenerator;

class DefaultResourceInstaller
{
    public function __construct(
        private readonly MigrationGenerator $migrationGenerator,
        private readonly ModelGenerator $modelGenerator,
        private readonly CrudDefinitionGenerator $resourceGenerator,
        private readonly ControllerGenerator $controllerGenerator,
        private readonly ReactPageGenerator $reactPageGenerator,
        private readonly RouteGenerator $routeGenerator,
    ) {}

    /**
     * Install all registered default CRUD resources automatically.
     *
     * @param  Command  $command  The calling command instance (for postInstall hook)
     * @return array<int, DefaultCrud>
     */
    public function install(Command $command): array
    {
        $registry = app(DefaultCrudRegistry::class);
        $features = [];

        foreach ($registry->all() as $resource) {
            $command->info("  Installing: {$resource->name()}");

            $this->installMigration($resource);
            $this->installModel($resource);
            $this->installResource($resource);
            $this->installController($resource);
            $this->installPages($resource);
            $this->installRoutes($resource);
            $this->installSeeder($resource);

            $resource->postInstall($command);

            $features[] = $resource;
        }

        return $features;
    }

    protected function installMigration(DefaultCrud $resource): void
    {
        $this->migrationGenerator->generate(
            $resource->modelName(),
            $resource->fields(),
            [],
            $resource->options()['timestamps'] ?? true,
            $resource->options()['softDeletes'] ?? false,
            []
        );
    }

    protected function installModel(DefaultCrud $resource): void
    {
        $this->modelGenerator->generate(
            $resource->modelName(),
            $resource->fields(),
            $resource->options()['softDeletes'] ?? false,
            []
        );
    }

    protected function installResource(DefaultCrud $resource): void
    {
        $this->resourceGenerator->generate(
            $resource->modelName(),
            $resource->fields(),
            []
        );
    }

    protected function installController(DefaultCrud $resource): void
    {
        $customStubs = $resource->customStubs();

        $methods = $resource->controllerMethods();
        if (isset($customStubs['controller-index'])) {
            $methods = array_diff($methods, ['index']);
        }

        $this->controllerGenerator->generate(
            $resource->modelName(),
            'inertia',
            $methods,
            $resource->fields()
        );

        if (isset($customStubs['controller-index'])) {
            $this->appendControllerMethod(
                $resource->modelName(),
                $resource,
                $customStubs['controller-index']
            );
        }

        if (isset($customStubs['controller-method'])) {
            $this->appendControllerMethod(
                $resource->modelName(),
                $resource,
                $customStubs['controller-method']
            );
        }
    }

    protected function installPages(DefaultCrud $resource): void
    {
        $customStubs = $resource->customStubs();
        $pagesPath = config('bulba.react_pages_path', 'admin');
        $pagesDir = resource_path("js/pages/{$pagesPath}/{$resource->modelName()}");

        File::ensureDirectoryExists($pagesDir);

        if (isset($customStubs['index-page'])) {
            $stubPath = $this->getResourceStubPath($resource, $customStubs['index-page']);
            if (File::exists($stubPath)) {
                File::put($pagesDir.'/Index.tsx', File::get($stubPath));
            }
        } else {
            $this->reactPageGenerator->generate(
                $resource->modelName(),
                $resource->fields(),
                []
            );
        }
    }

    protected function installRoutes(DefaultCrud $resource): void
    {
        $this->routeGenerator->generate(
            $resource->modelName(),
            'inertia',
            $resource->controllerMethods()
        );
    }

    protected function installSeeder(DefaultCrud $resource): void
    {
        if (! $resource->seederClass() || ! $resource->seederStub()) {
            return;
        }

        $stubPath = $this->getResourceStubPath($resource, $resource->seederStub());
        $destination = database_path('seeders/'.$resource->seederClass().'.php');

        if (File::exists($destination)) {
            return;
        }

        if (File::exists($stubPath)) {
            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, File::get($stubPath));
        }
    }

    protected function getResourceStubPath(DefaultCrud $resource, string $stubName): string
    {
        $reflection = new \ReflectionClass($resource);
        $resourceDir = dirname($reflection->getFileName());

        return $resourceDir.'/stubs/'.$stubName.'.stub';
    }

    protected function appendControllerMethod(string $modelName, DefaultCrud $resource, string $stubName): void
    {
        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $controllerPath = app_path(
            str_replace('\\', '/', str_replace('App\\', '', $namespace))."/{$modelName}Controller.php"
        );

        if (! File::exists($controllerPath)) {
            return;
        }

        $stubPath = $this->getResourceStubPath($resource, $stubName);
        if (! File::exists($stubPath)) {
            return;
        }

        $content = File::get($controllerPath);
        $methodStub = File::get($stubPath);

        if (preg_match('/public function (\w+)\(/', $methodStub, $matches)) {
            $methodName = $matches[1];
            if (str_contains($content, "public function {$methodName}(")) {
                return;
            }
        }

        if (! str_contains($content, 'use Illuminate\\Http\\Request;')) {
            $content = str_replace(
                'use Inertia\\Inertia;',
                "use Illuminate\\Http\\Request;\nuse Inertia\\Inertia;",
                $content
            );
        }

        $modelImport = "use App\\Models\\{$modelName};";
        if (! str_contains($content, $modelImport)) {
            $content = str_replace(
                'use Inertia\\Inertia;',
                "{$modelImport}\nuse Inertia\\Inertia;",
                $content
            );
        }

        $pagesPath = config('bulba.react_pages_path', 'admin');
        $methodStub = str_replace(
            ['{{ model }}', '{{ modelLower }}', '{{ pagesPath }}'],
            [$modelName, Str::lower($modelName), $pagesPath],
            $methodStub
        );

        $content = preg_replace('/\}\s*$/', $methodStub."\n}", $content);

        File::put($controllerPath, $content);
    }
}
