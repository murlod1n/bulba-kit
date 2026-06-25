# AGENTS.md

## What this is

Laravel package (`nktlksvch/bulba-kit`) that generates admin CRUD resources: migrations, models, controllers, React/Inertia pages (TSX + shadcn/ui), routes, and AI config. PHP 8.5+.

## Commands

```bash
composer install                  # install dependencies
vendor/bin/phpunit                # run all tests
vendor/bin/phpunit --testsuite=Unit        # unit tests only
vendor/bin/phpunit --testsuite=Integration # integration tests only
vendor/bin/phpunit --filter=ModelGeneratorTest # single test class
vendor/bin/phpstan analyse        # static analysis (level 6, src/)
vendor/bin/phpstan analyse --memory-limit=512M  # if default memory limit is hit
vendor/bin/pint                   # check code style (dry-run: --test)
vendor/bin/pint --test            # verify without modifying files
```

Always run `vendor/bin/phpstan analyse` and `vendor/bin/pint --test` after making changes to `src/`.

## Architecture

- **Namespace**: `Nktlksvch\BulbaKit\` (src), `Nktlksvch\BulbaKit\Tests\` (tests)
- **Service provider**: `Providers/BulbaKitServiceProvider` — auto-discovered via `composer.json` `extra.laravel.providers`
- **Two artisan commands**: `bulba:make` (interactive CRUD generator) and `bulba:install` (full React+Inertia admin panel setup for host app)
- **7 generators** in `src/Generators/`: `MigrationGenerator`, `ModelGenerator`, `ResourceGenerator`, `ControllerGenerator`, `ReactPageGenerator`, `RouteGenerator`, `AiConfigGenerator`
- **Builders** in `src/Generators/Builders/`: `FieldsBuilder`, `RelationsBuilder`, `ValidationRulesBuilder`, `ArrayRenderer` — helper classes used by generators
- **Stubs**: code templates in `src/Resources/stubs/` (resource generation) and `src/Resources/install-stubs/` (host app install)
- **ReactPageGenerator** generates `.tsx` files using shadcn/ui components (Table, Card, Button, Field/Input/Textarea/Select/Checkbox, Pagination, AlertDialog, DropdownMenu, Badge)
- **bulba:install** initializes shadcn/ui with `--base base --preset b3lno3MAK --template next` and installs all required components including `field`, `table`, `textarea`, `pagination`, `alert-dialog`
- **Abstract resource**: `src/AbstractResource.php` — base class that generated resource classes extend; defines `model()`, `fields()`, `validationRules()`, `relations()` contract
- **Schema inspector**: `src/Services/SchemaInspector.php` — database introspection for relationship configuration
- **Config**: `config/bulba.php` — published as `bulba` in host app. Comments are in Russian, this is intentional.

## Page Structure

- All admin pages live in `resources/js/pages/admin/` (lowercase `pages`, lowercase `admin`)
- Auth pages: `resources/js/pages/admin/auth/` (login, register, forgot-password, etc.)
- Settings pages: `resources/js/pages/admin/settings/` (profile, security, appearance)
- Dashboard: `resources/js/pages/admin/dashboard.tsx`
- CRUD pages: `resources/js/pages/admin/{ModelName}/` (Index, Create, Edit, Show, Form)
- No `resolve` function in `app.tsx` — Inertia resolves pages from the default `resources/js/Pages/` directory
- `app.blade.php` uses `@vite` with `resources/js/pages/{$page['component']}.tsx`

## Route Generation

- **Route files**: `routes/admin.php` (inertia), `routes/admin-api.php` (API)
- **Parent require**: `ensureRequireInParent()` always called (not just on first create) to ensure `routes/web.php` requires `routes/admin.php`
- **Kebab-case**: `Str::kebab()` converts model names to URL-friendly slugs (e.g., `WebsiteSetting` → `website-settings`)
- **Prefix**: `config('bulba.route_prefix')` (default: `admin`) prepended to all resource routes

## Module System

Modules are pre-built, opinionated components installed via `bulba:install`. Each module provides migrations, models, controllers, React pages, routes, navigation items, and optional seeders/middleware.

- **Interface**: `src/Modules/Contracts/ModuleInterface.php`
- **Registry**: `src/Modules/ModuleRegistry.php` — registered in `BulbaKitServiceProvider::register()` as singleton
- **Modules live in** `src/Modules/{ModuleName}/` with a `ModuleNameModule.php` class and `stubs/` directory
- **Modules reuse existing generators** (MigrationGenerator, ModelGenerator, etc.) with pre-defined field arrays
- **Module-specific stubs** (custom pages, middleware) are in each module's `stubs/` directory
- **Install flow**: `bulba:install` step 12 = `multiselect` module picker, step 13 = install selected modules
- **Navigation**: each module declares `navigation(): array` returning groups/items; collected into `resources/js/navigation.ts`
- **Installed modules** recorded in `config/bulba.php` under `'modules'` key
- **Custom stubs keys**:
  - `index-page` — custom React index page (replaces standard generated Index.tsx)
  - `controller-index` — custom index controller method (replaces standard `inertia-index.stub`)
  - `controller-method` — additional controller method appended after standard methods

### Current Modules

- **WebsiteSettings** (`src/Modules/WebsiteSettings/`): key-value settings with groups (general, seo, contacts, social, ecommerce). Model: `WebsiteSetting`, table: `website_settings`. Custom index page with tabs. Custom index controller method (groups settings by group). Seeder with defaults. Bulk update controller method. Route: `/admin/website-settings`.
- **Redirects** (`src/Modules/Redirects/`): URL redirects (301/302). Custom index page with inline create/delete. `RedirectMiddleware` with cache. Registered in `bootstrap/app.php`. Route: `/admin/redirects`.

## Media Integration

`spatie/laravel-medialibrary` is a core dependency installed by `bulba:install`.

- **Field type `image`** in `AsksForFields` — asks for collection name, thumb width/height
- **FieldsBuilder**: image fields produce virtual fields (`_url`, `_thumb`, `_alt`) instead of DB columns
- **ValidationRulesBuilder**: skips image fields (not DB columns)
- **ModelGenerator**: when image fields exist → adds `implements HasMedia`, `use InteractsWithMedia`, `registerMediaCollections()` (singleFile), `registerMediaConversions()` (thumb + webp)
- **ControllerGenerator**: when image fields exist → uses media-aware store/update stubs, adds `handleMediaUpload()`, `handleMediaRemoval()` methods
- **ReactPageGenerator**: image fields render `<ImageUpload>` component instead of `<Input>`
- **ImageUpload component**: `src/Resources/install-stubs/js/components/ui/image-upload.tsx.stub` — preview, replace, remove, alt text input, AI generate button placeholder

## Navigation System

- **Generated config**: `resources/js/navigation.ts` — created by `bulba:install`, updated by module installation
- **Types**: `NavGroup` (label + items), `NavItem` (title, href, icon string)
- **Icon resolver**: `resources/js/lib/icon-map.ts` — maps string names to Lucide components
- **NavMain component**: accepts `NavGroup[]`, renders multiple `SidebarGroup` with separators
- **AppSidebar**: reads from `@/navigation` import, passes to `NavMain`
- **NavFooter**: uses `resolveIcon` for external link icons
- **AppHeader**: uses string icons + `resolveIcon` (not Lucide components directly)

## Testing

- **Framework**: PHPUnit + Orchestra Testbench
- **Base class**: `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`
- **Temp dirs**: each test gets a unique temp dir in `sys_get_temp_dir()` (auto-cleaned in tearDown)
- **Integration tests** use SQLite `:memory:` — configured in `CrudOperationsTest::setUp()`
- **Generator tests** call generators directly (not via artisan), then assert generated file contents
- **Module tests**: `tests/Unit/Modules/` — test ModuleRegistry and each module's configuration
- **Media tests**: `tests/Unit/Generators/MediaFieldTest.php` — test HasMedia, media collections, media conversions, virtual fields
- **Helpers**: `assertFileContains()`, `assertFileNotContains()` — custom assertions on base TestCase
- **Test pattern**: generators write to temp dir → test reads file → asserts content with `assertStringContainsString`

## Gotchas

- `config/bulba.php` doc blocks are in Russian — leave them as-is
- `vendor/` and `composer.lock` are in `.gitignore` — this is a library, lock file exists locally but is not committed
- No CI workflows configured for this package (no `.github/workflows/`)
- Install stubs (`src/Resources/install-stubs/`) are for the host app, not for this package's own code
- `pnpm-workspace.yaml` is only copied if pnpm is installed (shadcn assumes pnpm monorepo otherwise)
- shadcn components `icon` and `placeholder-pattern` do not exist in the `b3lno3MAK` preset — do not add them
- `TooltipProvider` uses `delay` prop (not `delayDuration`) in base-ui
- `NavItem.href` is `NonNullable<InertiaLinkProps['href']>` to support both strings and Wayfinder route objects
- Module stubs use direct URL strings (`/admin/redirects`) instead of `route()` (Ziggy) since Wayfinder is the route system
- PageProps interfaces must include `[key: string]: unknown` index signature for Inertia compatibility
- MigrationGenerator skips creating migrations if one for the same table already exists (deduplication)
