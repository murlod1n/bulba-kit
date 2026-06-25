<?php

namespace Nktlksvch\BulbaKit\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Nktlksvch\BulbaKit\Providers\BulbaKitServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/bulba_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [BulbaKitServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bulba.database', 'sqlite');
        $app['config']->set('bulba.controller_namespace', 'App\\Http\\Controllers\\Admin');
        $app['config']->set('bulba.resource_namespace', 'App\\Resources');
        $app['config']->set('bulba.react_pages_path', 'Admin');
        $app['config']->set('bulba.auto_register_routes', false);
        $app['config']->set('bulba.ai_enabled', false);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    protected function assertFileContains(string $path, string $needle): void
    {
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString($needle, $content, "File {$path} does not contain expected content.");
    }

    protected function assertFileNotContains(string $path, string $needle): void
    {
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringNotContainsString($needle, $content, "File {$path} unexpectedly contains content.");
    }
}
