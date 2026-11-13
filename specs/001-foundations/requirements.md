# 001 — Foundations: Requirements

> Status: Approved (2026-11-13)
> Scope: infrastructure (Docker, Composer, CI, tooling), `app/Shared`, `tests/Architecture`, `routes`/`bootstrap`, deletion of all legacy code. No business module.

## Context

The prototype runs Laravel 12 on PHP 8.2 and MySQL 8.0, and it has three problems:

- **Auth:** it authenticates through a Redis lookup on an external `redis-network`.
- **Data:** it stores money as `double`, per user, with no household.
- **Checks:** it has no CI. Its only tests are two scaffold examples that run on SQLite in memory.

Before modules (Identity, Household, Ledger) can be built from specs, the repo needs:

- a supported runtime;
- a test database that behaves like production;
- code rules enforced on **all** code;
- one error format.

This spec builds that ground and deletes the prototype. It adds no user-facing feature. After it, the API serves only `GET /api/v1/health` until spec 002.

### Owner decisions

| Topic | Decision |
|---|---|
| Database | MySQL **8.4 LTS** in Docker and CI. PostgreSQL is dropped. |
| Redis | Removed entirely. Cache, queue, sessions and rate limits use Laravel's `database` drivers. |
| UUID storage | `CHAR(36)` (`ascii`). |
| Legacy code | The architecture rules, Pint and Larastan apply to **all** code. Legacy code is **deleted** in this spec rather than refactored; specs 002–005 rebuild its features properly. This includes the legacy `users`/`sessions` migration; Identity (spec 002) recreates both. |
| Error classes and `RequireJson` | Built in this project's `app/Shared`, adapted from Shop Keeper's reference. No shared package dependency. |
| Error envelope | Shop Keeper's: `{"error": {"code": …, "fields": …}}`, with per-field **codes** (`amount.required`), never messages. |
| 500 body | Generic in production. Exception details are added only when `APP_DEBUG=true`. |
| Currencies | Every ISO 4217 currency ICU knows, exponent taken from `ext-intl`. |
| Health | `GET /api/v1/health` checks the database and is throttled on the `file` cache store. Laravel's `/up` is removed. |
| Scaffold tests | `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php` are deleted (approved). |
| Leftover config | The redis, memcached, dynamodb and sqs blocks are removed from `config/`. Front-end scaffolding, `laravel/sail` and `scripts/migrate_to_postgres.sh` stay. |
| `composer audit` | Runs in CI on every PR. |
| `mysql-network` | The uncommitted named network is dropped. |

### What exists today

| Area | Current state |
|---|---|
| `composer.json` | `php ^8.2`, `laravel/framework ^12.0`, `laravel/sanctum ^4.0`. Dev dependencies: `phpunit/phpunit ^11.5`, `friendsofphp/php-cs-fixer ^3.89`, `laravel/pint`, `laravel/sail`, `laravel/pail`. No Pest, no Larastan. |
| `Dockerfile.dev` | `php:8.2-fpm` and `composer:latest` (unpinned). Installs with `--no-dev`, so the tooling is missing in the container. Runs `pecl install redis`. Copies `.env.example` to `.env`. |
| `docker-compose.yml` | App, nginx, `mysql:8.0` (container `mysql`, port 3306 published on all interfaces), and phpMyAdmin (logs in as root, port 8080). External `redis-network`. An uncommitted named `mysql-network`. The app connects as `root`. |
| `.env` / `.env.example` | `.env.example` is tracked in git and holds a real `APP_KEY` and the root DB password. `REDIS_*` keys are present. `.env` has `REDIS_PASSWORD`, which `.env.example` lacks. Cache, queue and sessions already use the `database` drivers. |
| `phpunit.xml` | Tests run on `sqlite` `:memory:`. Suites: `tests/Unit` and `tests/Feature`. |
| Tests | Two scaffold `ExampleTest`s. The feature one calls `GET /`, which has no route. |
| Style | `.php-cs-fixer.dist.php`, plus a `.php-cs-fixer.cache` that is tracked in git. |
| `bootstrap/app.php` | Aliases `redis.auth` and `query.user`. A validation renderer returns `{"errors": [messages]}`. `/up` is enabled. |
| `routes/api.php` | Profile, categories and money-transition routes behind the Redis middlewares, under `api/v1`. |
| Legacy code | `app/Http` (controllers, middlewares, requests, resources), `app/Models`, `app/Repositories`, `app/Services`, `app/Pagination`, migrations `0001_01_01_000000_create_users_table` (custom users with `auth_id`; sessions cascade-deleted from users), `2025_11_03_190136_categories` and `2025_11_04_190748_money_transition`, `database/seeders/*`, `database/factories/UserFactory.php`. `config/auth.php` points to `App\Models\User`. |
| Kept from Laravel | Migrations `…000001_create_cache_table` and `…000002_create_jobs_table`; `app/Providers/AppServiceProvider.php`. |
| Missing | No `.github/`, no `app/Shared`, no `app/Modules`, no `config/sanctum.php`. |

## User stories

- **US-1** — As the developer, I want the app on Laravel 13 and PHP ≥ 8.4.27, so that it runs on a supported, patched runtime.
- **US-2** — As the developer, I want MySQL 8.4 with a least-privilege app user, a separate test database and strict SQL, so that tests behave like production and bad data is rejected.
- **US-3** — As the developer, I want Redis removed, so that the stack needs one less service.
- **US-4** — As the developer, I want Pest, Pint and Larastan in place on all code, so that every change is tested, formatted and type-checked the same way.
- **US-5** — As the developer, I want architecture tests that enforce the module rules on all code, so that modules stay decoupled without relying on review.
- **US-6** — As a client developer (Angular / Capacitor), I want every API error as a stable machine code, so that the app can translate it and react without parsing text.
- **US-7** — As a client developer, I want the API to accept JSON only and always answer in JSON, so that request handling is predictable.
- **US-8** — As a module developer, I want a `Money` value object in integer minor units, so that amounts are never floats and currency exponents are handled in one place.
- **US-9** — As an operator, I want `GET /api/v1/health`, so that I can check that the API and its database are up.
- **US-10** — As the owner, I want CI on every pull request, so that nothing merges without passing Pint, Larastan, the tests and the dependency audit.
- **US-11** — As the developer, I want configuration and docs that match the stack, so that `.env.example`, Docker and CLAUDE.md never lie.
- **US-12** — As the owner, I want the prototype's legacy code deleted, so that every remaining line follows the rules and later specs build on a clean base.

## Acceptance criteria (EARS)

### US-1 — Runtime

- **REQ-1.1** THE SYSTEM SHALL run on a PHP image pinned to a version ≥ 8.4.27 and < 8.5 in `Dockerfile.dev`.
- **REQ-1.2** THE SYSTEM SHALL require `php` ≥ 8.4.27 and `laravel/framework` ^13 in `composer.json`, with every other dependency on a version compatible with Laravel 13 (Sanctum included).
- **REQ-1.3** IF the Composer platform reports a PHP version below 8.4.27 THEN `composer install` SHALL fail.
- **REQ-1.4** THE SYSTEM SHALL pin the Composer image to an explicit version instead of `composer:latest`.
- **REQ-1.5** WHEN the app container is built for local development, the image SHALL include the dev dependencies, so that Pest, Pint and Larastan run inside the container.
- **REQ-1.6** WHEN `php artisan about` runs in the container, it SHALL report Laravel 13.x and PHP ≥ 8.4.27.
- **REQ-1.7** THE SYSTEM SHALL apply the Laravel 12 → 13 upgrade steps to `bootstrap/` and `config/`, so that the app boots and `php artisan route:list --path=api` succeeds.

### US-2 — MySQL

- **REQ-2.1** THE SYSTEM SHALL run MySQL 8.4 (LTS), with the image pinned to an 8.4.x tag, in `docker-compose.yml` and in CI.
- **REQ-2.2** THE SYSTEM SHALL connect to MySQL as a dedicated app user, never as `root`.
  - The user's privileges are limited to `expenses_db` and `expenses_test`.
  - It has no global privileges and no `GRANT OPTION`.
- **REQ-2.3** WHEN the MySQL volume is created for the first time, THE SYSTEM SHALL create `expenses_db`, `expenses_test` and the app user automatically, with no manual SQL.
- **REQ-2.4** WHILE tests run, THE SYSTEM SHALL use the MySQL database `expenses_test`, never SQLite and never `expenses_db`.
- **REQ-2.5** IF the configured test database name equals the development database name THEN the test run SHALL abort before it migrates or truncates anything.
- **REQ-2.6** THE SYSTEM SHALL run MySQL in strict mode, at both the server `sql_mode` and the Laravel connection (`strict => true`), with at least these flags active:
  - `STRICT_TRANS_TABLES`
  - `ONLY_FULL_GROUP_BY`
  - `NO_ZERO_DATE`
  - `NO_ZERO_IN_DATE`
  - `ERROR_FOR_DIVISION_BY_ZERO`
- **REQ-2.7** IF a write would truncate a string, insert an invalid date or overflow a numeric column THEN the database SHALL reject it with an error, not a warning. A test proves this against `expenses_test`.
- **REQ-2.8** THE SYSTEM SHALL use `utf8mb4` for the databases and the connection, and a UTC session time zone.
- **REQ-2.9** THE SYSTEM SHALL publish the MySQL and phpMyAdmin ports on `127.0.0.1` only. phpMyAdmin is marked local-only in `docker-compose.yml`.
- **REQ-2.10** THE SYSTEM SHALL store primary and foreign UUID keys as `CHAR(36)` with the `ascii` character set (Laravel's `uuid()` column, ordered UUIDs from `HasUuids`). This binds specs 002+.
- **REQ-2.11** THE SYSTEM SHALL remove the named `mysql-network` from `docker-compose.yml`. The app and phpMyAdmin reach MySQL over the project network only.

### US-3 — Remove Redis

- **REQ-3.1** THE SYSTEM SHALL delete `RedisAuthMiddleware` and `QueryUserMiddleware` and their aliases in `bootstrap/app.php`.
- **REQ-3.2** THE SYSTEM SHALL remove `redis-network` from `docker-compose.yml` and the `phpredis` extension from `Dockerfile.dev`.
- **REQ-3.3** THE SYSTEM SHALL remove every `REDIS_*` key from `.env` and `.env.example`.
- **REQ-3.4** THE SYSTEM SHALL use Laravel's `database` drivers for cache, cache locks, rate limiting and queue. The `cache` and `jobs` migrations they need stay. The `sessions` table comes back with Identity (spec 002), and no route in this spec starts a session.
- **REQ-3.5** THE SYSTEM SHALL remove the `redis`, `memcached`, `dynamodb` and `sqs` blocks from `config/cache.php`, `config/queue.php`, `config/session.php` and `config/database.php`. The `database`, `array`, `file`, `null` and `sync` options are kept where they apply.
- **REQ-3.6** WHEN the codebase outside `vendor/` is searched for `redis`, `memcached`, `dynamodb` or `sqs` (case-insensitive), THE SYSTEM SHALL return no match.

### US-4 — Tooling

- **REQ-4.1** THE SYSTEM SHALL use Pest as the test runner, discover tests in `app/Modules/*/Tests`, `app/Shared` tests (location set in design) and `tests/Architecture`, and run them with `php artisan test`.
- **REQ-4.2** THE SYSTEM SHALL delete `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php` (owner approved).
- **REQ-4.3** THE SYSTEM SHALL use Pint for code style. `friendsofphp/php-cs-fixer`, `.php-cs-fixer.dist.php` and the tracked `.php-cs-fixer.cache` are removed.
- **REQ-4.4** WHEN Pint runs in test mode on a file that breaks its rules, it SHALL exit non-zero.
- **REQ-4.5** THE SYSTEM SHALL configure Pint to enforce `declare(strict_types=1);` and `final` classes on all PHP code in `app/`, `bootstrap/`, `config/`, `database/`, `routes/` and `tests/`.
- **REQ-4.6** THE SYSTEM SHALL run Larastan at level 6 or higher on all of `app/`, and fail on any error.
  - No path is excluded.
  - No error is silenced by a baseline or an ignore rule.
- **REQ-4.7** THE SYSTEM SHALL provide Composer scripts for tests, Pint (fix and test mode), Larastan and audit that match the commands in CLAUDE.md.

### US-5 — Architecture tests (all code)

- **REQ-5.1** THE SYSTEM SHALL have Pest `arch()` tests in `tests/Architecture` that apply to all code under `app/`, and to every module under `app/Modules/*` automatically. Adding a module needs no change to the tests.
- **REQ-5.2** IF `app/` contains a top-level namespace other than `App\Modules`, `App\Shared` and `App\Providers` THEN the architecture tests SHALL fail. This keeps legacy layers (`App\Http`, `App\Models`, `App\Repositories`, `App\Services`, `App\Pagination`) from coming back.
- **REQ-5.3** IF code in `App\Shared` or `App\Modules\Identity` uses another module THEN the architecture tests SHALL fail.
- **REQ-5.4** IF a module uses a module not listed for it in CLAUDE.md (e.g. `App\Modules\Household` using `App\Modules\Ledger`) THEN the architecture tests SHALL fail. `App\Shared` is always allowed.
- **REQ-5.5** IF a module uses another module's `Models` namespace THEN the architecture tests SHALL fail, except for `App\Modules\Identity\Models\User`.
- **REQ-5.6** IF `app/` contains any of the following THEN the architecture tests SHALL fail:
  - a class whose name ends in `Repository` or `Service`;
  - a class named `Base*`;
  - a class extending such a class.

  Laravel's `ServiceProvider` subclasses are the one exception.
- **REQ-5.7** IF a class in a module's `Actions` namespace is not `final`, or has a public method other than `__construct` and `handle`, THEN the architecture tests SHALL fail.
- **REQ-5.8** IF a module contains an `Http` namespace (controllers belong under `Controllers/`) THEN the architecture tests SHALL fail.
- **REQ-5.9** IF a PHP file under `app/` lacks `declare(strict_types=1);`, or a class there is not `final`, THEN the architecture tests SHALL fail.
  - Exceptions are only those a spec explicitly allows (e.g. Eloquent models, if design needs them non-final).
- **REQ-5.10** IF `env()` is called outside `config/` THEN the architecture tests SHALL fail.
- **REQ-5.11** THE SYSTEM SHALL show during development that each rule in REQ-5.2 – REQ-5.10 fails on a deliberate violation, because few classes exist yet and the rules would otherwise pass vacuously. The violation is not committed.

### US-6 — Error format (`ApiException` + renderer)

- **REQ-6.1** THE SYSTEM SHALL provide `ApiException` and a JSON error renderer in `app/Shared`, adapted from Shop Keeper's reference without depending on it.
  - `ApiException` carries a stable machine code (`<domain>.<reason>`, lowercase snake segments) and an HTTP status.
- **REQ-6.2** WHEN any exception reaches the renderer for a request under `/api/`, THE SYSTEM SHALL respond with the envelope `{"error": {"code": "<code>", …}}`.
  - The body holds no user-facing message.
  - Optional members appear only when they apply: `fields`, `retry_after_seconds`, and `debug` (REQ-6.5).
- **REQ-6.3** WHEN validation fails, THE SYSTEM SHALL respond `422` with code `validation.failed` and `fields`.
  - `fields` maps each field path (dot notation for nested fields, e.g. `items.0.amount`) to a list of codes `<field>.<rule>` in snake case, e.g. `{"amount": ["amount.required"]}`.
  - It holds no translated messages.
  - Custom rules produce codes in the same `<field>.<reason>` form.
- **REQ-6.4** THE SYSTEM SHALL map framework exceptions to these codes:

  | Situation | Status | Code |
  |---|---|---|
  | Not authenticated | 401 | `auth.unauthenticated` |
  | Authorization refused | 403 | `auth.forbidden` |
  | Unknown route, or route model not found with no module-specific code | 404 | `request.not_found` |
  | Method not allowed | 405 | `request.method_not_allowed` |
  | Body not JSON (US-7) | 415 | `request.not_json` |
  | Validation failed | 422 | `validation.failed` + `fields` |
  | Rate limited | 429 | `request.throttled`, plus `retry_after_seconds` and the `Retry-After` header |
  | Maintenance mode | 503 | `server.unavailable` |
  | Anything else | 500 | `server.error` |

- **REQ-6.5** IF an unexpected exception occurs THEN THE SYSTEM SHALL return `500` `server.error`.
  - WHILE `APP_DEBUG=false`, the body SHALL contain no stack trace, exception message, class name, SQL or file path.
  - WHILE `APP_DEBUG=true`, the body MAY add a `debug` member with the exception class, message, file, line and trace.
  - A test proves both modes.
- **REQ-6.6** THE SYSTEM SHALL give every API response an `X-Request-Id` header. WHEN a `5xx` error is logged, the log entry SHALL include that request id and SHALL NOT include personal data (request body, emails, amounts, tokens).
- **REQ-6.7** THE SYSTEM SHALL replace the legacy validation renderer in `bootstrap/app.php`.
- **REQ-6.8** WHEN a request outside `/api/` fails (e.g. `/sanctum/csrf-cookie`), THE SYSTEM SHALL keep Laravel's default rendering.

### US-7 — JSON only (`RequireJson`)

- **REQ-7.1** THE SYSTEM SHALL provide a `RequireJson` middleware in `app/Shared` and apply it to every `/api/v1` route.
- **REQ-7.2** IF a request has a body (raw content, form fields or files) and its `Content-Type` is not JSON, or the body is not valid JSON, THEN THE SYSTEM SHALL respond `415` `request.not_json` and not run the route.
- **REQ-7.3** WHEN a request has no body (e.g. `GET`, `DELETE`), THE SYSTEM SHALL accept it whatever its `Content-Type`.
- **REQ-7.4** THE SYSTEM SHALL answer `/api/v1` requests in JSON whatever the `Accept` header, errors included.
- **REQ-7.5** IF a JSON body is valid JSON but not an object or array at the top level (e.g. `"abc"`, `42`) THEN THE SYSTEM SHALL respond `415` `request.not_json`.

### US-8 — `Money`

- **REQ-8.1** THE SYSTEM SHALL provide an immutable, `final`, `readonly` `Money` in `app/Shared`, holding an integer amount in minor units and an ISO 4217 currency code.
- **REQ-8.2** THE SYSTEM SHALL take each currency's exponent from ICU (`ext-intl`), e.g. TND → 3 (millimes), EUR → 2 (cents), JPY → 0. No currency is hard-coded.
- **REQ-8.3** IF `Money` is built with a code that is not three letters or that ICU does not know as a currency THEN it SHALL throw a domain exception. Codes are uppercased, so `tnd` is accepted as `TND`.
- **REQ-8.4** THE SYSTEM SHALL accept only `int` amounts. No float, decimal string or numeric string is accepted anywhere in `Money`'s API.
- **REQ-8.5** THE SYSTEM SHALL allow zero and negative amounts in `Money`. Whether a given field accepts them is decided by each feature's spec.
- **REQ-8.6** WHEN two `Money` values of the same currency are added or subtracted, THE SYSTEM SHALL return a new `Money` with the exact integer result.
- **REQ-8.7** IF an operation or comparison mixes currencies THEN `Money` SHALL throw a domain exception and never convert.
- **REQ-8.8** IF an addition or subtraction would exceed PHP's 64-bit integer range (which matches MySQL `BIGINT`) THEN `Money` SHALL throw a domain exception instead of producing a float.
- **REQ-8.9** THE SYSTEM SHALL provide equality, `isZero`, `isNegative`, `isPositive` and ordering comparisons (same currency only).
- **REQ-8.10** THE SYSTEM SHALL have unit tests asserting exact integers for each rule, including:
  - TND 1.000 = `1000`, EUR 1.00 = `100`, JPY 1 = `1`;
  - rejection of `XYZ` and `EURO`.

### US-9 — Health

- **REQ-9.1** WHEN `GET /api/v1/health` is called without authentication and the database answers, THE SYSTEM SHALL respond `200` `{"status": "ok"}` (final shape in `contract.md`).
- **REQ-9.2** IF the database is unreachable THEN `GET /api/v1/health` SHALL respond `503` `{"error": {"code": "health.database_unavailable"}}`.
- **REQ-9.3** THE SYSTEM SHALL keep the health response free of versions, host names, configuration and timings.
- **REQ-9.4** THE SYSTEM SHALL rate-limit the health route by `$request->ip()` on Laravel's `file` cache store, never the database, so the limiter adds no database load and still works while MySQL is down. Exceeding the limit returns `429` per REQ-6.4. All other rate limits stay on the database store. (Amended 2026-11-13 by owner decision during design.)
- **REQ-9.5** IF `POST`/`PUT`/`PATCH`/`DELETE` is sent to `/api/v1/health` THEN THE SYSTEM SHALL respond `405` `request.method_not_allowed`.
- **REQ-9.6** THE SYSTEM SHALL remove Laravel's `/up` route. `GET /up` returns `404`.
- **REQ-9.7** THE SYSTEM SHALL have feature tests for REQ-9.1 – REQ-9.6. Validation and household-isolation tests are not applicable: the route takes no input and no household.

### US-10 — CI

- **REQ-10.1** WHEN a pull request is opened or updated against `main`, or a push lands on `main`, a GitHub Actions workflow SHALL run:
  - Pint (test mode);
  - Larastan;
  - `composer audit`;
  - `php artisan test` against a MySQL 8.4 service, using `expenses_test` in strict mode.
- **REQ-10.2** THE SYSTEM SHALL run CI on the same PHP minor version (≥ 8.4.27) and the same PHP extensions as `Dockerfile.dev`.
- **REQ-10.3** IF Pint, Larastan, `composer audit` or the tests fail THEN the workflow SHALL fail.
- **REQ-10.4** THE SYSTEM SHALL run CI with no repository secrets and no real credentials. Throwaway values are used for the CI database only.

### US-11 — Configuration and docs

- **REQ-11.1** THE SYSTEM SHALL keep `.env.example` with exactly the same keys as `.env`. It holds safe placeholder values and empty secrets: no `APP_KEY` value and no DB passwords.
- **REQ-11.2** THE SYSTEM SHALL treat the `APP_KEY` and DB passwords committed in `.env.example` as exposed. The local `APP_KEY` and DB passwords are regenerated; no data needs to be kept.
- **REQ-11.3** THE SYSTEM SHALL stop `Dockerfile.dev` from copying `.env.example` to `.env` inside the image. The app reads `.env` from the mounted project, or fails clearly when it is missing.
- **REQ-11.4** THE SYSTEM SHALL keep CLAUDE.md in line with this spec (updated during the requirements phase).
- **REQ-11.5** THE SYSTEM SHALL keep the Docker setup working from a fresh clone. The only manual steps are copying `.env.example` to `.env`, filling in secrets, and running `docker compose up -d --build`.

### US-12 — Delete legacy code

- **REQ-12.1** THE SYSTEM SHALL delete:
  - `app/Http`, `app/Models`, `app/Repositories`, `app/Services` and `app/Pagination`;
  - the legacy migrations `0001_01_01_000000_create_users_table`, `2025_11_03_190136_categories` and `2025_11_04_190748_money_transition`;
  - `database/seeders/*`;
  - `database/factories/UserFactory.php`;
  - every legacy route in `routes/api.php`.
- **REQ-12.2** WHEN legacy code is deleted, THE SYSTEM SHALL leave no reference to it in `bootstrap/`, `config/`, `routes/`, `database/` or `tests/`.
  - This includes `config/auth.php` (`App\Models\User`).
  - The app boots, and `php artisan migrate:fresh` succeeds on an empty database.
- **REQ-12.3** WHEN a request reaches a former legacy path (e.g. `GET /api/v1/categories`, `POST /api/v1/money-transition`), THE SYSTEM SHALL respond `404` `request.not_found`.
- **REQ-12.4** THE SYSTEM SHALL make every PHP file that remains under `app/` pass the architecture tests (US-5), Pint and Larastan (US-4).
- **REQ-12.5** IF a later spec needs a behaviour from the prototype THEN that spec SHALL define it anew; no prototype code is restored from git.

Checklist:
- [x] Validation errors (per field, with stable codes): REQ-6.3. Health takes no input.
- [x] Unauthenticated access (401): REQ-6.4. Cookie and token modes are N/A until spec 002.
- [ ] Access to another household's data: N/A (no household data in this spec).
- [x] Rate limits / repeated requests / duplicates: REQ-6.4, REQ-9.4.
- [x] Money edge cases (zero, negative, currency exponent, overflow, mixed currency, unknown currency): REQ-8.x.
- [ ] Date & timezone edge cases: only the UTC session time zone (REQ-2.8); household time zone is N/A.
- [x] Deleting / changing referenced data: legacy deletion, REQ-12.x.

## Non-goals

- Authentication (Sanctum cookie/token, sign-up, codes), the `users` and `sessions` tables, and `config/sanctum.php`: spec 002.
- Households, categories, transactions, summaries, and any module folder under `app/Modules`.
- Rebuilding any prototype feature (profile, categories, money transitions).
- The JSON shape of `Money` in API responses: spec 004 (Transactions).
- A shared Composer package for errors or `RequireJson`.
- A queue worker container: added by the first spec that dispatches a job.
- Removing the front-end scaffolding (`package.json`, `vite.config.js`, `resources/`, the npm steps in Composer scripts), `laravel/sail` or `scripts/migrate_to_postgres.sh` (owner decision: they stay).
- A production Docker image, hosting, a deploy pipeline, and `trustProxies`.
- GitHub branch protection rules. The owner sets these; this spec provides the check to require.
- The frontend app (`expenses-management`).

## Open questions

None. All questions were answered by the owner on 2026-11-13 (see **Owner decisions**).
