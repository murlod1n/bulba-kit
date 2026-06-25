<?php

namespace Nktlksvch\BulbaKit\Generators\Concerns;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

/**
 * LoadsStubs
 *
 * Provides stub file loading for generators. Checks for user-override stubs
 * in the application's stubs/bulba/ directory before falling back to package stubs.
 * This allows users to customize generated code output.
 */
trait LoadsStubs
{
    /**
     * Get a stub file content by name.
     *
     * Checks for a user-override stub at stubs/bulba/{name}.stub first,
     * then falls back to the package-bundled stub.
     *
     * @param  string  $name  Stub name (without .stub extension)
     * @return string Stub file content
     *
     * @throws FileNotFoundException
     */
    protected function getStub(string $name): string
    {
        $userStub = base_path("stubs/bulba/{$name}.stub");
        $packageStub = $this->getPackageStubPath($name);
        $path = File::exists($userStub) ? $userStub : $packageStub;

        return File::get($path);
    }

    /**
     * Get a stub file from a subdirectory by name.
     *
     * Used for nested stub structures like controller method stubs
     * (e.g., controllers/methods/inertia-index.stub).
     *
     * @param  string  $subDir  Subdirectory path relative to stubs root
     * @param  string  $name  Stub name (without .stub extension)
     * @return string Stub file content
     *
     * @throws FileNotFoundException
     */
    protected function getSubStub(string $subDir, string $name): string
    {
        $userStub = base_path("stubs/bulba/{$subDir}/{$name}.stub");
        $packageStub = $this->getPackageStubPath("{$subDir}/{$name}");
        $path = File::exists($userStub) ? $userStub : $packageStub;

        return File::get($path);
    }

    /**
     * Get the absolute path to a package-bundled stub file.
     *
     * @param  string  $name  Stub name (without .stub extension, may include subdirectory)
     * @return string Absolute file path
     */
    protected function getPackageStubPath(string $name): string
    {
        return __DIR__.'/../../Resources/stubs/'.$name.'.stub';
    }
}
