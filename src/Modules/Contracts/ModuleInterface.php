<?php

namespace Nktlksvch\BulbaKit\Modules\Contracts;

use Nktlksvch\BulbaKit\Console\Commands\BulbaInstallCommand;
use Nktlksvch\BulbaKit\Modules\ModuleRegistry;

/**
 * Contract for installable modules.
 *
 * Modules are optional, user-selected components that provide complex functionality
 * beyond standard CRUD (middleware, caching, WebSocket support, etc.).
 * Unlike DefaultCrud resources, modules have full control over their installation
 * logic via the install() method.
 *
 * Modules are selected via multiselect during `bulba:install` and their
 * navigation items are merged into the sidebar.
 *
 * @see ModuleRegistry
 * @see BulbaInstallCommand::selectModules()
 */
interface ModuleInterface
{
    /**
     * Module display name (e.g., 'Redirects').
     */
    public function name(): string;

    /**
     * Short description shown in the module selector.
     */
    public function description(): string;

    /**
     * Lucide icon name as string (e.g., 'arrow-right-left').
     */
    public function icon(): string;

    /**
     * Install the module.
     *
     * Called during `bulba:install` after the user selects this module.
     * The implementation has full control over what gets generated:
     * migrations, models, controllers, pages, routes, middleware, etc.
     *
     * Use app() to resolve generators (MigrationGenerator, ModelGenerator, etc.)
     * and $command->info() for progress output.
     *
     * @param  object  $command  The BulbaInstallCommand instance.
     */
    public function install(object $command): void;

    /**
     * Navigation items for the sidebar.
     *
     * Merged into the generated navigation.ts alongside DefaultCrud navigation.
     *
     * @return array<int, array{group: string, items: array<int, array{title: string, href: string, icon: string}>}>
     */
    public function navigation(): array;
}
