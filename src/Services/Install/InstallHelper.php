<?php

namespace Nktlksvch\BulbaKit\Services\Install;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class InstallHelper
{
    public function __construct(
        private readonly string $stubsPath,
        private readonly bool $force = false,
    ) {}

    public function stubsPath(): string
    {
        return $this->stubsPath;
    }

    public function force(): bool
    {
        return $this->force;
    }

    public function copyStub(string $stubPath, string $destination): void
    {
        $fullStubPath = $this->stubsPath.'/'.$stubPath;

        if (! File::exists($fullStubPath)) {
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($fullStubPath, $destination);
    }

    public function copyStubIfNotExists(string $stubPath, string $destination): void
    {
        if (File::exists($destination) && ! $this->force) {
            return;
        }

        $this->copyStub($stubPath, $destination);
    }

    public function isPackageInstalled(string $package): bool
    {
        $composerLockPath = base_path('composer.lock');
        if (! File::exists($composerLockPath)) {
            return false;
        }

        $lock = json_decode(File::get($composerLockPath), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        foreach ($packages as $installed) {
            if ($installed['name'] === $package) {
                return true;
            }
        }

        return false;
    }

    public function isCommandAvailable(string $command): bool
    {
        $process = Process::fromShellCommandline(
            "which {$command}",
            base_path(),
            ['PATH' => getenv('PATH')],
            null,
            10
        );
        $process->run();

        return $process->isSuccessful();
    }

    public function executeCommand(string $command, bool $verbose = false): bool
    {
        $process = Process::fromShellCommandline(
            $command,
            base_path(),
            ['PATH' => getenv('PATH')],
            null,
            300
        );

        $process->run(function ($type, $buffer) use ($verbose) {
            if ($verbose) {
                echo $buffer;
            }
        });

        if (! $process->isSuccessful()) {
            \Laravel\Prompts\warning("  Command failed: {$command}");
            $errorOutput = $process->getErrorOutput();
            if (! empty($errorOutput)) {
                \Laravel\Prompts\warning('  Error: '.substr($errorOutput, 0, 500));
            }

            return false;
        }

        return true;
    }
}
