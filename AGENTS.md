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
- **bulba:install** initializes shadcn/ui and installs all required components including `field`, `table`, `textarea`, `pagination`
- **Abstract resource**: `src/AbstractResource.php` — base class that generated resource classes extend; defines `model()`, `fields()`, `validationRules()`, `relations()` contract
- **Schema inspector**: `src/Services/SchemaInspector.php` — database introspection for relationship configuration
- **Config**: `config/bulba.php` — published as `bulba` in host app. Comments are in Russian, this is intentional.

## Testing

- **Framework**: PHPUnit + Orchestra Testbench
- **Base class**: `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`
- **Temp dirs**: each test gets a unique temp dir in `sys_get_temp_dir()` (auto-cleaned in tearDown)
- **Integration tests** use SQLite `:memory:` — configured in `CrudOperationsTest::setUp()`
- **Generator tests** call generators directly (not via artisan), then assert generated file contents
- **Helpers**: `assertFileContains()`, `assertFileNotContains()` — custom assertions on base TestCase
- **Test pattern**: generators write to temp dir → test reads file → asserts content with `assertStringContainsString`

## Gotchas

- `config/bulba.php` doc blocks are in Russian — leave them as-is
- `vendor/` and `composer.lock` are in `.gitignore` — this is a library, lock file exists locally but is not committed
- No CI workflows configured for this package (no `.github/workflows/`)
- Install stubs (`src/Resources/install-stubs/`) are for the host app, not for this package's own code
