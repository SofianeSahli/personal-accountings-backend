# 001 — Foundations: Design

> Status: Approved (2026-11-13)
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Spec 001 is plumbing plus a clean-out, in five parts:

1. **Runtime and Docker:** PHP 8.4.27, Laravel 13, MySQL 8.4, no Redis.
2. **Tests:** they run on MySQL `expenses_test`.
3. **Tooling:** Pint, Larastan and Pest `arch()` cover all of `app/`.
4. **`app/Shared`:** gets the API foundations: errors, `RequireJson`, request id, `Money`, and health.
5. **Clean-out:** every legacy file is deleted, and CI runs the whole gate on every PR.

Versions are taken from what Shop Keeper already runs in production-like use, so they are known to work together:

- `php:8.4.27-fpm-alpine`
- `composer:2.11.1`
- `mysql:8.4.13`
- Laravel `^13.17`
- Pest `^5.3`

At implementation time each one is bumped to the newest patch of the same line, and the exact tag is pinned.

## Placement

| Piece | Path | Reason / REQ |
|---|---|---|
| `ApiException` | `app/Shared/Errors/ApiException.php` | Machine code + status + optional `fields`, `retryAfterSeconds`. REQ-6.1 |
| `ApiErrorRenderer` | `app/Shared/Errors/ApiErrorRenderer.php` | Maps every throwable on `api/*` to the `{"error": …}` envelope. REQ-6.2–6.5, 7.4 |
| `ValidationCodes` | `app/Shared/Errors/ValidationCodes.php` | Turns `Validator::failed()` into `<field>.<rule>` codes; ignores messages. REQ-6.3 |
| `RequireJson` | `app/Shared/Http/RequireJson.php` | 415 on non-JSON bodies. REQ-7.1–7.5 |
| `AssignRequestId` | `app/Shared/Http/AssignRequestId.php` | Global middleware: id in `Context` (and so in logs) and the `X-Request-Id` header. REQ-6.6 |
| `Currency` | `app/Shared/Money/Currency.php` | ISO 4217 code + exponent from ICU. REQ-8.2, 8.3 |
| `Money` | `app/Shared/Money/Money.php` | Integer minor units, arithmetic, comparisons. REQ-8.1, 8.4–8.9 |
| `MoneyException` | `app/Shared/Money/MoneyException.php` | `final`, extends `DomainException`; named constructors `unknownCurrency`, `currencyMismatch`, `overflow`. REQ-8.3, 8.7, 8.8 |
| `CheckHealth` | `app/Shared/Health/CheckHealth.php` | Action: pings the DB, throws `health.database_unavailable`. REQ-9.1, 9.2 |
| `HealthController` | `app/Shared/Health/HealthController.php` | Invokable, thin: calls `CheckHealth` and returns the resource. REQ-9.1 |
| `HealthResource` | `app/Shared/Health/HealthResource.php` | `{"data": {"status": "ok"}}`. REQ-9.1, 9.3 |
| `HealthThrottle` | `app/Shared/Health/HealthThrottle.php` | `final` subclass of Laravel's `ThrottleRequests` bound to a `RateLimiter` on the `file` store. REQ-9.4 |
| Shared tests | `app/Shared/Tests/{Unit,Feature}` | Unit: Money, Currency, ValidationCodes. Feature: errors, RequireJson, request id, health, strict mode. |
| Architecture tests | `tests/Architecture/*Test.php` + `tests/Architecture/Support/ModuleMap.php` | US-5 |
| Repo-wide guards | `tests/Architecture/RepositoryGuardsTest.php` | `env()` outside `config/` in non-class files; no Redis-family names (REQ-3.6, 5.10) |

Health lives in `Shared` rather than a new module: it is cross-cutting, owns no table, and CLAUDE.md says to create a module only when a spec needs one. Nothing in `Shared` uses a module, so the dependency direction holds.

`app/Providers/AppServiceProvider.php` stays. It is the only class outside `Modules` and `Shared` (REQ-5.2), made `final` with strict types.

## Data

No table is created. Migrations after this spec:

| Migration | Fate |
|---|---|
| `0001_01_01_000000_create_users_table` | **Deleted** (REQ-12.1). Identity recreates `users` and `sessions` in spec 002. |
| `0001_01_01_000001_create_cache_table` | Kept: `cache`, `cache_locks` (database cache store and DB rate limits). Strict types added. |
| `0001_01_01_000002_create_jobs_table` | Kept: `jobs`, `job_batches`, `failed_jobs` (database queue). Strict types added. |
| `2025_11_03_190136_categories`, `2025_11_04_190748_money_transition` | **Deleted** (REQ-12.1). |

`CHAR(36)` UUIDs (REQ-2.10) are set up as a convention only, so spec 002 is the first to use them:

- `config/database.php` keeps the default `utf8mb4` charset.
- Module migrations call `$table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin')` (and the same on FK columns).
- An architecture test cannot check column types, so this rule goes in CLAUDE.md and code review.

## Runtime and Docker (US-1, US-2, US-3, US-11)

### `Dockerfile.dev`

- `FROM php:8.4.27-fpm-alpine`.
- `COPY --from=composer:2.11.1`.
- Extensions via `mlocati/php-extension-installer` (pinned): `intl opcache pcntl pdo_mysql zip`.
  - `gd` and `exif` are dropped; only legacy code might have used them.
  - `redis` is dropped (REQ-3.2).
- `docker/php/app.ini` sets:
  - `zend.exception_ignore_args=On`, so traces carry no argument values and no personal data reaches the logs;
  - `expose_php=Off`.
- `composer install` **with** dev dependencies (REQ-1.5). This image is the dev image; a production image is a non-goal.
- The `cp .env.example .env` line is removed (REQ-11.3). Without a mounted `.env`, Laravel fails at boot with a missing `APP_KEY`, which is the clear failure we want.

### `docker-compose.yml`

**`mysql`**
- `image: mysql:8.4.13`.
- Environment from `.env`, with `:?` so a missing secret stops `docker compose up`:
  - `MYSQL_DATABASE=${DB_DATABASE}`
  - `MYSQL_USER=${DB_USERNAME}`
  - `MYSQL_PASSWORD=${DB_PASSWORD:?}`
  - `MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD:?}`
- `command:` `--sql-mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --default-time-zone=+00:00` (REQ-2.6, 2.8).
- `docker/mysql/init/01-test-database.sh` (mounted at `/docker-entrypoint-initdb.d`, runs only on a new volume) creates `${DB_TEST_DATABASE}` and grants `ALL PRIVILEGES ON \`${DB_TEST_DATABASE}\`.*` to `${DB_USERNAME}`. The official image already grants that user all rights on `MYSQL_DATABASE` only. No global rights and no `GRANT OPTION` (REQ-2.2, 2.3).
- `ports: 127.0.0.1:${DB_FORWARD_PORT:-3306}:3306` (REQ-2.9).
- Health check: `mysqladmin ping`.
- Networks: the project network only. `mysql-network` and `redis-network` are removed (REQ-2.11, 3.2).

**`personal-accounting-backend`**
- `depends_on: mysql: condition: service_healthy`.
- Only the project network.

**`phpmyadmin`**
- `ports: 127.0.0.1:8080:80`.
- Logs in as root with `${DB_ROOT_PASSWORD}`.
- Labelled `local only` (REQ-2.9).

### `.env` / `.env.example` (REQ-11.1, 11.2)

- **Keys added:**
  - `DB_TEST_DATABASE=expenses_test`
  - `DB_ROOT_PASSWORD=`
  - `DB_FORWARD_PORT=3306`
- **Values changed:**
  - `DB_USERNAME=expenses` (no longer `root`)
  - `DB_PASSWORD=` (empty in the example)
  - `APP_KEY=` (empty in the example)
- **Keys removed:** `REDIS_*` and `MEMCACHED_HOST` (REQ-3.3).
- **Kept:** `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`.
- The local `.env` gets a new `APP_KEY` (`php artisan key:generate`) and new DB passwords. The MySQL volume is recreated, since there is no data to keep.

### Composer (REQ-1.2, 1.3, 4.3, 4.7)

**`require`**
- `"php": "~8.4.27"` (≥ 8.4.27, < 8.5)
- `laravel/framework ^13.17`
- `laravel/sanctum` on the Laravel 13 compatible line
- `laravel/tinker`

**`require-dev`**
- Added: `pestphp/pest ^5.3`, `pestphp/pest-plugin-laravel ^5.0`, `larastan/larastan` (the Laravel 13 line).
- Kept: `laravel/pint`, `laravel/pail`, `laravel/sail` (owner decision), `mockery/mockery`, `nunomaduro/collision`, `fakerphp/faker` (factories arrive with spec 002).
- Removed: `friendsofphp/php-cs-fixer`, and `phpunit/phpunit` as a direct dependency (Pest brings it).

No `config.platform.php` override, so Composer checks the real PHP version and refuses below 8.4.27 (REQ-1.3).

**Scripts**
- `test`: `config:clear`, then `artisan test`
- `lint`: `pint`
- `lint:check`: `pint --test`
- `analyse`: `phpstan analyse`
- `audit`: `composer audit`
- `setup` and `dev` are kept with their npm steps (front-end scaffolding stays).

### Laravel 12 → 13 (REQ-1.7)

Apply the official upgrade guide to `bootstrap/` and `config/`. Configuration changes:

- `bootstrap/app.php` drops `health: '/up'` (REQ-9.6) and the legacy aliases and validation renderer (REQ-3.1, 6.7).
- `config/cache.php`, `queue.php`, `session.php`, `database.php` lose the `redis`, `memcached`, `dynamodb` and `sqs` blocks and the comments naming them (REQ-3.5).
- `config/database.php` mysql connection: `strict => true` (already), `timezone => '+00:00'`, `charset => utf8mb4` (REQ-2.6, 2.8).
- `config/auth.php` users provider: `model => App\Modules\Identity\Models\User::class`, without `env('AUTH_MODEL')`. This is a forward reference to spec 002; `::class` does not autoload, so it is harmless until a guard resolves a user (REQ-12.2).

## Middleware and exception wiring

`bootstrap/app.php`:

```php
->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'api/v1', commands: __DIR__.'/../routes/console.php')
->withMiddleware(function (Middleware $middleware): void {
    $middleware->prepend(AssignRequestId::class);
    $middleware->api(append: [RequireJson::class]);
})
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*'));
    $exceptions->dontReport(ApiException::class);
    $exceptions->report(function (QueryException $exception): false {
        Log::error('database.query_failed', ['sql_state' => $exception->getCode()]);
        return false;
    });
    $exceptions->render(new ApiErrorRenderer());
})
```

- **`AssignRequestId`** is global (prepended), so it also wraps unmatched `/api` paths, which never reach the `api` group.
  - It always generates a new id (`Str::uuid7()`) and never trusts an incoming header, which would allow log injection.
  - It calls `Context::add('request_id', $id)`; Laravel attaches context to every log entry.
  - It sets `X-Request-Id` on the response. Rendered exceptions come back through the same pipeline, so error responses carry the header too (REQ-6.6).
- **`QueryException` reporting** logs only the SQL state. The default message contains SQL and bindings, which can hold personal data (REQ-6.6).

### `ApiErrorRenderer` mapping (REQ-6.4)

The renderer returns `null` for requests outside `api/*`, which keeps Laravel's default rendering (REQ-6.8). Render callbacks receive the **original** exception, before Laravel's own conversion, so both forms are mapped:

| Exception | Status | Code |
|---|---|---|
| `ApiException` | its own | its own |
| `ValidationException` | 422 | `validation.failed` + `fields` (from `ValidationCodes`) |
| `AuthenticationException` | 401 | `auth.unauthenticated` |
| `AuthorizationException`, `AccessDeniedHttpException` | 403 | `auth.forbidden` |
| `ModelNotFoundException`, `RecordsNotFoundException`, `NotFoundHttpException` | 404 | `request.not_found` |
| `MethodNotAllowedHttpException` | 405 | `request.method_not_allowed` |
| `TooManyRequestsHttpException` (includes `ThrottleRequestsException`) | 429 | `request.throttled` + `retry_after_seconds` (from `Retry-After`), header kept |
| `HttpException` with status 503 (maintenance mode) | 503 | `server.unavailable` |
| anything else | 500 | `server.error` |

- **Debug output:** when `config('app.debug')` is true and the status is 500, the body gains a `debug` member: `{exception, message, file, line, trace: ["file:line", …]}`. Otherwise it never does (REQ-6.5).
- **Envelope:** `{"error": {"code": …}}`. Empty optional members are omitted.

### `ValidationCodes` (REQ-6.3)

The renderer builds `fields` from `$exception->validator->failed()`, never from `errors()`, so translations and custom messages can't leak into the response:

- **Key:** the failing attribute path as Laravel reports it, e.g. `items.0.amount`.
- **Code:** the path with numeric segments removed, then `.`, then the rule name in snake case, e.g. `items.amount.required`.
  - Dropping the indexes gives the frontend one translation key per field, not one per row.
- **Rule name:**
  - String rules give their own name, so `Required` becomes `required` and `RequiredWithout` becomes `required_without`.
  - Rule objects give their class basename, so `InternationalPhone` becomes `international_phone`.
  - Closure rules would yield `closure_validation_rule`. Specs must use named rule classes; CLAUDE.md states this (added with this spec).
- Codes for one field keep Laravel's order and are de-duplicated.

### `RequireJson` (REQ-7.2–7.5)

This is a port of Shop Keeper's middleware, with one change.

- **Has a body:** non-empty raw content, any form field, or any file.
- **Accepted only when:**
  - `$request->isJson()` (`application/json` or `+json`);
  - `json_validate()` passes;
  - the decoded top-level value is an array or object (REQ-7.5, the change).
- **Otherwise:** `throw new ApiException('request.not_json', 415)`.
- Requests without a body pass through (REQ-7.3).

## `Money` (US-8)

### `Currency`

`final readonly`, with `code` and `exponent`. It is built with `Currency::of(string $code)`:

1. Uppercase the code, then require `/^[A-Z]{3}$/`. Otherwise `MoneyException::unknownCurrency` (rejects `EURO`).
2. Require the code to be in ICU's currency list (`ResourceBundle::create('en', 'ICUDATA-curr')['Currencies']`). Otherwise `unknownCurrency` (rejects `XYZ`).
3. Read the exponent from ICU supplemental data, `CurrencyMeta[<code>][0]`, falling back to `CurrencyMeta['DEFAULT'][0]`. This is the same source Symfony Intl uses: TND 3, EUR 2, JPY 0.

Results are memoised per code in a static array, so ICU is read once per process.

### `Money`

`final readonly`, holding `int $minorUnits` and `Currency $currency`.

- **Constructor:** `Money::of(int $minorUnits, Currency|string $currency)`. With `declare(strict_types=1)` and `int` parameters, floats and numeric strings are refused by PHP itself (REQ-8.4); tests assert the `TypeError`.
- **Methods:**
  - arithmetic: `plus(Money)`, `minus(Money)`;
  - comparison: `equals`, `compareTo` (−1/0/1), `greaterThan`, `lessThan`;
  - sign checks: `isZero`, `isNegative`, `isPositive`;
  - accessors: `minorUnits()`, `currency()`.
- **Rules:**
  - Every two-operand method first checks the currency (REQ-8.7).
  - Overflow (REQ-8.8): PHP turns an overflowing `int + int` into a float, so the method computes the result, checks `is_int`, and throws `MoneyException::overflow` if not. Tests use `PHP_INT_MAX`.
  - Zero and negative amounts are allowed (REQ-8.5).
- **Not provided:** no multiplication, allocation, formatting or JSON shape. Spec 004 adds what it needs.

## Health (US-9)

- **Route** (`routes/api.php`, the only route in the file):

  ```php
  Route::get('/health', HealthController::class)->middleware(HealthThrottle::with(maxAttempts: 60, decayMinutes: 1));
  ```

  The URL is `/api/v1/health`. Laravel answers other methods with `MethodNotAllowedHttpException`, which maps to 405 (REQ-9.5).
- **`HealthThrottle`:** `final class HealthThrottle extends ThrottleRequests`. Its constructor receives `CacheManager` and passes `new RateLimiter($cache->store('file'))` to the parent.
  - The numeric (unnamed) form of throttling uses that limiter directly.
  - Unauthenticated requests are keyed by domain and `$request->ip()`.
  - No database access, so it works while MySQL is down (REQ-9.4).
- **`CheckHealth::handle(): void`:** runs `$db->connection()->select('select 1')`. On `QueryException` or `PDOException` it throws `ApiException('health.database_unavailable', 503)` (REQ-9.2). Nothing else on the `/api` path touches the database: no session and no DB limiter.
- **`HealthController`:** calls `CheckHealth` and returns `new HealthResource('ok')`, which renders `{"data": {"status": "ok"}}`. There is no version, host or timing anywhere in the response (REQ-9.3).
- **`/up`:** removed (REQ-9.6).

## Tests and tooling (US-4, US-5)

### Test layout

- **`phpunit.xml` suites:**
  - `Architecture` → `tests/Architecture`
  - `Shared` → `app/Shared/Tests`
  - `Modules` → `app/Modules/*/Tests`
- **Forced env:**
  - `DB_CONNECTION=mysql`, `DB_DATABASE=expenses_test`
  - `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`
  - `BCRYPT_ROUNDS=4`
  - The sqlite lines go (REQ-2.4).
- **`tests/Pest.php`:** `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('../app/Shared/Tests/Feature', '../app/Modules/*/Tests')`.
- **Test DB guard:** `tests/TestCase.php` (abstract) overrides `createApplication()`. If `config('database.connections.mysql.database')` does not end in `_test`, it throws before any migration runs. This is stricter than "not equal to the dev name" and covers it (REQ-2.5).
- **Strict-mode test (REQ-2.7):**
  - In `app/Shared/Tests/Feature/MysqlStrictModeTest.php`.
  - It uses `CREATE TEMPORARY TABLE`, which, unlike normal DDL, does not commit `RefreshDatabase`'s transaction.
  - It asserts a `QueryException` for an overlong string, for `'2026-02-30'`, and for an out-of-range `TINYINT`.
- **Health "DB down" test:** points the connection at a nonexistent database (`config([...database => 'missing'])` + `DB::purge()`), which gives a fast and deterministic failure. `beforeEach` flushes the `file` cache store so throttle tests don't leak into each other.

### Pint (`pint.json`, REQ-4.3–4.5)

- Preset `laravel`, plus `declare_strict_types: true` and `final_class: true`.
- Excludes `bootstrap/cache` and `storage`.
- `final_class` leaves abstract classes (the `TestCase`) and anonymous migration classes alone.
- `.php-cs-fixer.dist.php` and `.php-cs-fixer.cache` are deleted.

### Larastan (`phpstan.neon`, REQ-4.6)

- Includes the Larastan extension, at `level: 6`, with `paths: [app]`.
- No `ignoreErrors`, no baseline file.
- Level 6 is CLAUDE.md's minimum. Raising it is cheap while the code is small, but that is left to a later change, so this spec doesn't depend on Laravel-magic edge cases at higher levels.

### Architecture tests (REQ-5.x)

`tests/Architecture/Support/ModuleMap.php` (final) is the CLAUDE.md table as data:

```php
public const ALLOWED = [
    'Identity'  => [],
    'Household' => ['Identity'],
    'Ledger'    => ['Household', 'Identity'],
];
```

`ModuleMap::modules()` lists the directories in `app/Modules` and adds any key of `ALLOWED`. A module missing from `ALLOWED` is allowed `Shared` only, so a new module is covered without editing the tests (REQ-5.1).

| File | Rule | REQ |
|---|---|---|
| `LayoutTest` | `app/` top-level directories ⊆ {`Modules`, `Shared`, `Providers`}; no `app/Modules/*/Http` directory | 5.2, 5.8 |
| `ModuleDependenciesTest` | For each module M: `expect("App\\Modules\\M")->not->toUse([...other modules not allowed])`. `App\Shared` and `App\Modules\Identity` must use no module. | 5.3, 5.4 |
| `ModuleModelsTest` | For each pair (M, X ≠ M): M must not use `App\Modules\X\Models\*`, except `App\Modules\Identity\Models\User`. Implemented by a loop over Pest arch's class-dependency list, because `not->toUse()` cannot exempt one class. | 5.5 |
| `NamingTest` | `expect('App')->classes()->not->toHaveSuffix('Repository')`, `->not->toHaveSuffix('Service')`, `->not->toHavePrefix('Base')`. `ServiceProvider` ends in `Provider`, so it passes. | 5.6 |
| `ActionsTest` | For each module: `Actions` classes `toBeFinal()` and `->not->toHavePublicMethodsBesides(['__construct', 'handle'])` | 5.7 |
| `StrictnessTest` | `expect('App')->toUseStrictTypes()`, `expect('App')->classes()->toBeFinal()` (no exception exists yet) | 5.9 |
| `RepositoryGuardsTest` | `expect('env')->not->toBeUsed()` for `App`, plus a file scan of `routes/`, `database/`, `bootstrap/` (excluding `bootstrap/cache`) for `env(`. A scan of `app bootstrap config database routes tests docker composer.json docker-compose.yml Dockerfile.dev .env.example` for `/redis|memcached|dynamodb|\bsqs\b/i` (excluding this test file itself) asserts no match. | 5.10, 3.6 |

**Proving the rules (REQ-5.11):** during the architecture task, each rule is shown to fail on a throwaway violating class (or directory), and the failing output is noted in `tasks.md`. Nothing from that is committed.

## CI (US-10)

`.github/workflows/ci.yml`:

- **Triggers:** `pull_request` to `main`, `push` to `main`.
- **Job `checks`:** `ubuntu-latest`.
  - Service `mysql:8.4.13`:
    - env `MYSQL_DATABASE=expenses_test`, `MYSQL_USER=expenses`, `MYSQL_PASSWORD=ci-only-password`, `MYSQL_ROOT_PASSWORD=ci-only-root` (throwaway, REQ-10.4);
    - health-checked with `mysqladmin ping`.
  - Steps:
    1. `actions/checkout`
    2. `shivammathur/setup-php` with `php-version: '8.4'` and `extensions: intl, opcache, pcntl, pdo_mysql, zip` (same as the Dockerfile, REQ-10.2). Then `php -r 'exit(version_compare(PHP_VERSION, "8.4.27", ">=") ? 0 : 1);'` to fail on an older runner image.
    3. Composer cache, then `composer install --no-interaction --no-progress`.
    4. `cp .env.example .env`, then `php artisan key:generate`. DB settings come from job `env` (`DB_HOST=127.0.0.1`, …).
    5. `vendor/bin/pint --test`
    6. `vendor/bin/phpstan analyse --no-progress`
    7. `composer audit`
    8. `php artisan test --compact`

    Any failing step fails the job (REQ-10.3).
- **Strict mode in CI:** GitHub service containers can't take `command:` arguments, so `--sql-mode` cannot be passed. MySQL 8.4's default `sql_mode` already contains all REQ-2.6 flags, Laravel's `strict => true` sets them per session, and the REQ-2.7 test fails if strictness is ever missing.
- **Third-party actions** are pinned to a full commit SHA, with the version in a comment, to protect the supply chain.

## Legacy deletion (US-12)

Deleted:

- `app/Http/**` (including `Controllers/Controller.php`, the legacy middlewares, requests and resources)
- `app/Models/**`, `app/Repositories/**`, `app/Services/**`, `app/Pagination/**`
- the three legacy migrations
- `database/seeders/**` and `database/factories/UserFactory.php`
- `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php`, then the empty `tests/Unit` and `tests/Feature`

`routes/api.php` is rewritten with only the health route. Former paths then match no route and get 404 `request.not_found` (REQ-12.3).

Check after deletion: `grep -rn "App\\\\(Http|Models|Repositories|Services|Pagination)"` over `bootstrap config database routes tests` returns nothing. `php artisan migrate:fresh` and `route:list` succeed (REQ-12.2).

## Endpoints

| Method | Path | Request | Response (resource) | Policy | Error codes |
|---|---|---|---|---|---|
| GET | `/api/v1/health` | none | `HealthResource` `{"data":{"status":"ok"}}` | none (public) | `health.database_unavailable` 503, `request.throttled` 429, `request.method_not_allowed` 405 |

Global behaviour (all `/api/v1` paths) and full examples are in [contract.md](./contract.md).

## Authorization

None. The only route is public by design (REQ-9.1). Policies start with spec 003.

## Error codes

- `validation.failed`: validation failed, 422, with `fields`.
- `auth.unauthenticated`: no or invalid credentials, 401.
- `auth.forbidden`: authorization refused, 403.
- `request.not_found`: unknown route or missing model, 404.
- `request.method_not_allowed`: wrong HTTP method, 405.
- `request.not_json`: body present but not a JSON object/array, 415.
- `request.throttled`: rate limit hit, 429, with `retry_after_seconds`.
- `server.unavailable`: maintenance mode, 503.
- `server.error`: unexpected failure, 500.
- `health.database_unavailable`: health check cannot reach MySQL, 503.

## CLAUDE.md additions with this spec

- **UUID columns:** `uuid()->charset('ascii')->collation('ascii_bin')`.
- **Validation rules:** use named rule classes, never closures, so field codes stay meaningful.

## Requirement coverage

| REQ | Covered by |
|---|---|
| 1.1, 1.4, 1.5 | `Dockerfile.dev` |
| 1.2, 1.3 | Composer (`~8.4.27`, no platform override) |
| 1.6, 1.7 | Upgrade guide + `bootstrap/app.php` / `config/*` |
| 2.1–2.3, 2.9, 2.11 | `docker-compose.yml` + `docker/mysql/init/01-test-database.sh` |
| 2.4 | `phpunit.xml` forced env |
| 2.5 | `TestCase::createApplication` `_test` guard |
| 2.6, 2.8 | MySQL `command:` + `config/database.php` |
| 2.7 | `MysqlStrictModeTest` |
| 2.10 | UUID convention (Data) + CLAUDE.md |
| 3.1–3.5 | Wiring, Docker, `.env`, config clean-up |
| 3.6 | `RepositoryGuardsTest` |
| 4.1, 4.2 | Test layout, `Pest.php`, deletions |
| 4.3–4.5 | `pint.json`, php-cs-fixer removal |
| 4.6 | `phpstan.neon` |
| 4.7 | Composer scripts |
| 5.1–5.10 | Architecture tests table |
| 5.11 | Proof step, recorded in `tasks.md` |
| 6.1–6.5, 6.7, 6.8 | `ApiException`, `ApiErrorRenderer`, `ValidationCodes`, wiring |
| 6.6 | `AssignRequestId`, `QueryException` report, `exception_ignore_args` |
| 7.1–7.5 | `RequireJson` + `shouldRenderJsonWhen` |
| 8.1–8.10 | `Currency`, `Money`, `MoneyException` + unit tests |
| 9.1–9.3, 9.5–9.7 | Health route, `CheckHealth`, `HealthResource`, feature tests |
| 9.4 | `HealthThrottle` on the `file` store |
| 10.1–10.4 | `.github/workflows/ci.yml` |
| 11.1–11.3, 11.5 | `.env.example`, Dockerfile, compose |
| 11.4 | CLAUDE.md (done) + additions above |
| 12.1–12.5 | Legacy deletion |

## Risks / alternatives considered

- **ICU vs ISO 4217:** ICU follows CLDR usage, and for a few currencies its digits differ from ISO (e.g. some with unused minor units). TND, EUR and the common currencies match, and tests pin them. If a household ever uses a divergent currency, a spec can add an override map.
- **`/sanctum/csrf-cookie`** starts a session, and the `sessions` table is gone until spec 002, so that route errors until then. No client calls it before spec 002.
- **Other `HttpException` statuses** (e.g. `abort(400)`) map to 500 `server.error`, as REQ-6.4 says. Code must throw `ApiException` with an explicit code instead of calling `abort()`.
- **The `file` limiter is per server.** With several app servers each enforces its own limit, which is acceptable for a health probe and noted for the hosting decision.
- **Alpine instead of Debian** for the PHP image: smaller, and the same base as Shop Keeper. The only extensions needed are available through `install-php-extensions`. A Debian base is the fallback if an extension misbehaves.
- **Final Eloquent models:** `final_class` and REQ-5.9 make models final. Laravel supports this. If spec 002 needs Identity's `User` to be extendable, that spec allows the exception explicitly.
- **Pest arch limits:** if `not->toHavePublicMethodsBesides` or the dependency listing doesn't behave as expected in Pest 5, the affected rule falls back to a reflection-based test in the same file. The REQ stays the same.
- **Considered:** a separate `System` module for health. Rejected: no table, no domain, and CLAUDE.md forbids speculative modules.
