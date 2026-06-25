<?php

namespace Nktlksvch\BulbaKit\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nktlksvch\BulbaKit\Generators\Concerns\LoadsStubs;

/**
 * Route Generator
 *
 * Generates Laravel route registrations for admin CRUD controllers.
 * Features:
 * - Auto-creates route file if it doesn't exist
 * - Adds use statements at the top of the file
 * - Ensures parent route file (web.php/api.php) requires the admin route file
 * - Supports both Inertia (Route::resource) and API (Route::apiResource) routes
 */
class RouteGenerator
{
    use LoadsStubs;

    /**
     * Generate route registration for a controller.
     *
     * @param  string  $name  Model name (e.g., 'Post')
     * @param  string  $type  Controller type ('inertia' or 'api')
     * @param  array<int, string>  $methods  Controller methods (unused, for interface compatibility)
     */
    public function generate(
        $name,
        $type = 'inertia',
        $methods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
    ): void {
        if (! config('bulba.auto_register_routes', true)) {
            return;
        }

        $routeFile = $this->getRouteFilePath($type);

        // Create route file if it doesn't exist
        if (! file_exists($routeFile)) {
            File::ensureDirectoryExists(dirname($routeFile));
            File::put($routeFile, $this->getStub('routes-'.$type.'-head'));
        }

        $this->ensureRequireInParent($type);

        $stub = $this->getStub('routes-'.$type);
        $resourceName = Str::plural(Str::kebab($name));

        // Get prefix and middleware based on type
        [$prefix, $middleware] = $this->getRouteConfig($type);

        $namespace = config('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $useStatement = "use {$namespace}\\{$name}Controller;";

        $routeEntry = str_replace(
            ['{{ prefix }}', '{{ resource }}', '{{ controllerNamespace }}', '{{ model }}', '{{ middleware }}'],
            [$prefix, $resourceName, $namespace, $name, implode("','", $middleware)],
            $stub
        );

        $content = File::get($routeFile);

        // Skip if route already registered
        if (Str::contains($content, "{$name}Controller::class")) {
            return;
        }

        // Insert use statement at the top
        if (! Str::contains($content, $useStatement)) {
            $content = $this->insertUseStatement($content, $useStatement);
        }

        // Append route entry
        $content = rtrim($content)."\n".$routeEntry;
        File::put($routeFile, $content);
    }

    /**
     * Get the route file path based on controller type.
     *
     * @param  string  $type  Controller type
     * @return string Route file path
     */
    protected function getRouteFilePath(string $type): string
    {
        return $type === 'api'
            ? base_path('routes/admin-api.php')
            : base_path('routes/admin.php');
    }

    /**
     * Get route configuration (prefix and middleware) based on type.
     *
     * @param  string  $type  Controller type
     * @return array{string, array<int, string>} [prefix, middleware]
     */
    protected function getRouteConfig(string $type): array
    {
        if ($type === 'api') {
            return [
                config('bulba.api_route_prefix', 'api/admin'),
                config('bulba.api_middleware', ['api', 'auth:sanctum']),
            ];
        }

        return [
            config('bulba.route_prefix', 'admin'),
            config('bulba.middleware', ['web', 'auth']),
        ];
    }

    /**
     * Insert a use statement after the last existing use statement.
     *
     * @param  string  $content  File content
     * @param  string  $useStatement  Use statement to insert
     * @return string Updated content
     */
    protected function insertUseStatement(string $content, string $useStatement): string
    {
        $lines = explode("\n", $content);
        $lastUseIndex = -1;

        // Find the last use statement
        foreach ($lines as $i => $line) {
            if (str_starts_with(trim($line), 'use ') && str_ends_with(trim($line), ';')) {
                $lastUseIndex = $i;
            }
        }

        if ($lastUseIndex >= 0) {
            // Insert after last use statement
            array_splice($lines, $lastUseIndex + 1, 0, [$useStatement]);
        } else {
            // Find end of header (after <?php and empty lines)
            $headerEnd = -1;
            foreach ($lines as $i => $line) {
                if (str_starts_with(trim($line), '<?php') || trim($line) === '') {
                    $headerEnd = $i;
                } else {
                    break;
                }
            }
            array_splice($lines, $headerEnd + 1, 0, ['', $useStatement]);
        }

        return implode("\n", $lines);
    }

    /**
     * Ensure the parent route file (web.php/api.php) requires the admin route file.
     *
     * @param  string  $type  Controller type
     */
    protected function ensureRequireInParent(string $type): void
    {
        $parentFile = $type === 'api'
            ? base_path('routes/api.php')
            : base_path('routes/web.php');

        $adminFile = $type === 'api'
            ? 'admin-api.php'
            : 'admin.php';

        if (! file_exists($parentFile)) {
            return;
        }

        $requireLine = "require __DIR__.'/$adminFile';";
        $content = File::get($parentFile);

        if (! Str::contains($content, $adminFile)) {
            File::append($parentFile, "\n".$requireLine."\n");
        }
    }
}
