<?php

namespace Nktlksvch\BulbaKit\Modules\Contracts;

interface ModuleInterface
{
    /**
     * Module display name (e.g., 'Website Settings').
     */
    public function name(): string;

    /**
     * Short description shown in the module selector.
     */
    public function description(): string;

    /**
     * Lucide icon name as string (e.g., 'settings', 'arrow-right-left').
     */
    public function icon(): string;

    /**
     * Eloquent model name (e.g., 'Setting', 'Redirect').
     */
    public function modelName(): string;

    /**
     * Database table name (e.g., 'settings', 'redirects').
     */
    public function tableName(): string;

    /**
     * Field definitions in the same format as bulba:make.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array;

    /**
     * Controller methods to generate (e.g., ['index', 'create', 'store']).
     *
     * @return array<int, string>
     */
    public function controllerMethods(): array;

    /**
     * Generation options (timestamps, softDeletes).
     *
     * @return array<string, bool>
     */
    public function options(): array;

    /**
     * Navigation items for the sidebar.
     *
     * @return array<int, array{group: string, items: array<int, array{title: string, href: string, icon: string}>}>
     */
    public function navigation(): array;

    /**
     * Seeder class name (e.g., 'SettingSeeder'), or null if no seeder.
     */
    public function seederClass(): ?string;

    /**
     * Seeder stub filename without extension, or null.
     */
    public function seederStub(): ?string;

    /**
     * Custom stubs that override standard generators.
     * Keys: 'index-page', 'controller', 'controller-method'.
     * Values: stub filename without extension.
     *
     * @return array<string, string>
     */
    public function customStubs(): array;

    /**
     * Post-install hook for module-specific setup (middleware registration, etc.).
     *
     * @param  object  $command  The install command instance (for copyStub, executeCommand, etc.)
     */
    public function postInstall(object $command): void;
}
