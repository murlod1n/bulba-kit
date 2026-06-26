# AGENTS.md

## What this is

Laravel package (`nktlksvch/bulba-kit`) that generates admin CRUD resources: migrations, models, controllers, React/Inertia pages (TSX + shadcn/ui), routes, AI config, and multilingual support. PHP 8.5+.

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
- **7 generators** in `src/Generators/`: `MigrationGenerator`, `ModelGenerator`, `CrudDefinitionGenerator`, `ControllerGenerator`, `ReactPageGenerator`, `RouteGenerator`, `AiConfigGenerator`
- **Builders** in `src/Generators/Builders/`: `FieldsBuilder`, `RelationsBuilder`, `ValidationRulesBuilder`, `ArrayRenderer` — helper classes used by generators
- **Stubs**: code templates in `src/Resources/stubs/` (resource generation) and `src/Resources/install-stubs/` (host app install)
- **ReactPageGenerator** generates `.tsx` files using shadcn/ui components (Table, Card, Button, Field/Input/Textarea/Select/Checkbox, Pagination, AlertDialog, DropdownMenu, Badge)
- **bulba:install** initializes shadcn/ui with `--base base --preset b3lno3MAK --template next` and installs all required components including `field`, `table`, `textarea`, `pagination`, `alert-dialog`
- **Abstract crud definition**: `src/AbstractCrudDefinition.php` — base class that generated definition classes extend; defines `model()`, `routeName()`, `pagePath()`, `fields()`, `validationRules()`, `relations()`, `mediaContracts()`
- **Schema inspector**: `src/Services/SchemaInspector.php` — database introspection for relationship configuration
- **Config**: `config/bulba.php` — published as `bulba` in host app. Comments are in Russian, this is intentional.

## Controller Traits

Generated controllers use trait-based architecture instead of inline methods. Each CRUD action is a separate trait.

- **Core traits** in `src/Traits/`:
  - `ResolveCrudDefinition` — resolves `#[CrudDefinition]` attribute to get the definition instance via `getCrudDefinition()`
  - `HasCrudHelpers` — shared helpers: `getSelectOptions()`, `getRelationNames()`, `syncBelongsToMany()`, `runAiGeneration()`
  - `HasMediaActions` — media upload/removal: `handleMediaUpload()`, `handleMediaRemoval()`, `updateMediaAlt()`
  - `HasTranslationHelpers` — translatable field support: `getTranslatableFields()`, `hasTranslatableFields()`, `applyTranslatableFields()`, `autoTranslateMissing()`
- **Action traits** in `src/Traits/Actions/Inertia/` (7): `HasInertiaIndexAction`, `HasInertiaCreateAction`, `HasInertiaStoreAction`, `HasInertiaShowAction`, `HasInertiaEditAction`, `HasInertiaUpdateAction`, `HasInertiaDestroyAction`
- **Action traits** in `src/Traits/Actions/Api/` (7): `HasApiIndexAction`, `HasApiCreateAction`, `HasApiStoreAction`, `HasApiShowAction`, `HasApiEditAction`, `HasApiUpdateAction`, `HasApiDestroyAction`
- **ControllerGenerator** generates a thin controller shell with trait `use` statements; methods live in traits
- **Media overrides**: when image fields exist, generator adds `HasMediaActions` + overrides `store()`/`update()` with media-aware stubs from `src/Resources/stubs/media-overrides/`
- **Translation support**: when translatable fields exist, generator adds `HasTranslationHelpers` trait
- **User override**: users can override any method directly in the controller class — PHP class methods take precedence over trait methods

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

## Default CRUD Resources

Pre-built, standard CRUD resources installed automatically during `bulba:install`. They follow the same structure as resources generated by `bulba:make` — no custom infrastructure, just migration + model + controller + pages + routes.

- **Interface**: `src/DefaultCrud/Contracts/DefaultCrud.php`
- **Registry**: `src/DefaultCrud/DefaultCrudRegistry.php` — registered as singleton in BulbaKitServiceProvider
- **Resources live in** `src/DefaultCrud/{ResourceName}/` with a `ResourceNameDefaultCrud.php` class and `stubs/` directory
- **Install flow**: `bulba:install` iterates all registered resources and generates migration, model, resource, controller, pages, routes, seeder automatically (no user selection)
- **Custom stubs keys** (same as modules):
  - `index-page` — custom React index page (replaces standard generated Index.tsx)
  - `controller-index` — custom index controller method (overrides trait `index()` method)
  - `controller-method` — additional controller method appended after standard methods
- **Navigation**: each resource declares `navigation(): array`; merged into `resources/js/navigation.ts`
- **Installed resources** recorded in `config/bulba.php` under `'features'` key

### Current Default Resources

- **WebsiteSettings** (`src/DefaultCrud/WebsiteSettings/`): key-value settings with groups (general, seo, contacts, social, ecommerce). Model: `WebsiteSetting`, table: `website_settings`. Custom index page with tabs. Custom index controller method (groups settings by group). Seeder with defaults. Bulk update controller method. Route: `/admin/website-settings`.

## Module System

Modules are optional, user-selected components that provide complex functionality beyond standard CRUD (middleware, caching, WebSocket support, etc.). Unlike DefaultCrud resources, modules have full control over their installation logic.

- **Interface**: `src/Modules/Contracts/ModuleInterface.php` — minimal contract: `name()`, `description()`, `icon()`, `install($command)`, `navigation()`
- **Registry**: `src/Modules/ModuleRegistry.php` — registered as singleton in BulbaKitServiceProvider
- **Modules live in** `src/Modules/{ModuleName}/` with a `ModuleNameModule.php` class and `stubs/` directory
- **Install flow**: `bulba:install` shows a multiselect prompt; each selected module's `install($command)` is called
- **Each module implements its own install logic** — calls generators directly via `app()`, copies stubs, registers middleware, etc.
- **Navigation**: each module declares `navigation(): array`; merged into `resources/js/navigation.ts` alongside DefaultCrud navigation
- **Installed modules** recorded in `config/bulba.php` under `'modules'` key

### Current Modules

- **Redirects** (`src/Modules/Redirects/`): URL redirects (301/302). Full CRUD + custom index page with inline create/delete. `RedirectMiddleware` with cache-based redirect lookup. Registered in `bootstrap/app.php`. Route: `/admin/redirects`.

## Media Integration

`spatie/laravel-medialibrary` is a core dependency installed by `bulba:install`.

- **Field type `image`** in `AsksForFields` — asks for collection name, thumb width/height
- **Field type `gallery`** in `AsksForFields` — same as image but multiple files, asks for collection name, thumb width/height, max files limit
- **FieldsBuilder**: keeps original image/gallery field with `type: 'image'`/`'gallery'` and `collection` property (no virtual fields)
- **MigrationGenerator**: skips image and gallery fields (not DB columns, handled by Media Library)
- **ValidationRulesBuilder**: skips image and gallery fields (not DB columns)
- **ModelGenerator**: when image fields exist → adds `implements HasMedia`, `use InteractsWithMedia`, `registerMediaCollections()` (singleFile for image, multi for gallery), `registerMediaConversions()` (thumb + webp), and accessor methods:
  - Image: `getImageUrlAttribute()`, `getImageThumbAttribute()`, `getImageAltAttribute()`
  - Gallery: `get{Name}Attribute()` returns `array[{id, url, thumb, alt}]`
- **ControllerGenerator**: when image/gallery fields exist → adds `HasMediaActions` trait + media-aware store/update overrides
- **ReactPageGenerator**: image fields render `<ImageUpload>` component, gallery fields render `<GalleryUpload>` component
- **Show page**: image renders `<img>` tag with full URL; gallery renders image grid
- **Index page**: image renders thumbnail (40x40); gallery renders stacked thumbnails with count badge
- **HasMediaActions**: `handleMediaUpload()` / `handleMediaRemoval()` for single images; `handleGalleryUpload()` / `handleGalleryRemoval()` / `handleGalleryReorder()` / `handleGalleryAlt()` for galleries
- **ImageUpload component**: `src/Resources/install-stubs/js/components/ui/image-upload.tsx.stub` — preview, replace, remove, alt text input, AI generate button placeholder
- **GalleryUpload component**: `src/Resources/install-stubs/js/components/ui/gallery-upload.tsx.stub` — drag-and-drop via `react-dropzone`, drag-to-reorder via `@dnd-kit`, per-image alt text, per-image remove button

## Navigation System

- **Generated config**: `resources/js/navigation.ts` — created by `bulba:install`, collects nav from both DefaultCrud resources and modules
- **Types**: `NavGroup` (label + items), `NavItem` (title, titleKey?, href, icon string)
- **Icon resolver**: `resources/js/lib/icon-map.ts` — maps string names to Lucide components
- **NavMain component**: accepts `NavGroup[]`, renders multiple `SidebarGroup` with separators; uses `useTrans()` hook for `t(titleKey ?? title)`
- **AppSidebar**: reads from `@/navigation` import, passes to `NavMain`
- **NavFooter**: uses `resolveIcon` for external link icons
- **AppHeader**: uses string icons + `resolveIcon` (not Lucide components directly)

## Multilingual System

Two layers of translations: **DB translations** (model content via spatie/laravel-translatable) and **UI translations** (static strings via JSON lang files).

### Configuration (`config/bulba.php`)

- `locales` — array of supported locale codes (e.g., `['en', 'ru']`)
- `default_locale` — default locale (e.g., `'en'`)
- `translation` — AI translation config (separate from main AI config): `ai_enabled`, `ai_provider`, `ai_model`, `ai_api_key`

### Install flow (`bulba:install`)

- Prompts user to select locales (multiselect: en, ru)
- Generates empty `lang/{locale}.json` files for each locale
- Installs `HandleLocale` middleware (reads locale from `?lang=` query param or session)
- Updates `HandleInertiaRequests` to share `locale`, `locales`, `translations` via Inertia props
- Installs `spatie/laravel-translatable` as a dependency

### `bulba:make` — translatable fields

- After collecting fields, asks which string/text fields should be translatable (only if >1 locale configured)
- Translatable fields are stored as JSON columns in DB (`$table->json()` instead of `$table->string()`)
- Model gets `HasTranslatable` interface + `HasTranslations` trait + `$translatable` array
- Validation uses per-locale rules: `title.en => ['required', 'string']`, `title.ru => ['required', 'string']`
- Fields marked with `'translatable' => true` in CrudDefinition

### React UI

- `LocaleSwitcher` component (`js/components/ui/locale-switcher.tsx`) — page-level tabs for switching active locale
- `useTrans()` hook (`js/hooks/use-trans.ts`) — provides `t(key)` function using Inertia shared translations
- Create/Edit pages show locale tabs above form when translatable fields exist
- Translatable fields display value for `activeLocale`; non-translatable fields are constant
- Two buttons: "Save" and "Save with auto-translation" (triggers AI translation of empty locales)
- Show/Index pages display translatable field values for current locale

### TranslationService (`src/Services/TranslationService.php`)

- `translate($text, $fromLocale, $toLocales)` — single text translation via AI
- `translateBatch($texts, $fromLocale, $toLocales)` — batch translation
- Uses OpenAI-compatible API (OpenRouter, OpenAI) configured in `config('bulba.translation')`

### GeneratesTranslations concern

- Called during `bulba:make` after all generators
- Appends UI strings (navigation labels, field labels, page titles, buttons) to `lang/{locale}.json`
- Default locale: key = value (English as-is); other locales: empty value (to be translated manually or by AI)

### HandleLocale middleware

- Priority: `?lang=xx` query param > session > `config('bulba.default_locale')`
- Stores selected locale in session for persistence

## Testing

- **Framework**: PHPUnit + Orchestra Testbench
- **Base class**: `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`
- **Temp dirs**: each test gets a unique temp dir in `sys_get_temp_dir()` (auto-cleaned in tearDown)
- **Integration tests** use SQLite `:memory:` — configured in `CrudOperationsTest::setUp()`
- **Generator tests** call generators directly (not via artisan), then assert generated file contents
- **DefaultCrud tests**: `tests/Unit/DefaultCrud/` — test DefaultCrudRegistry and each resource's configuration
- **Module tests**: `tests/Unit/Modules/` — test ModuleRegistry and each module's interface compliance
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
