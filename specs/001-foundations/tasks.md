# 001 — Foundations: Tasks

> Status: Done (2026-11-13), pending owner review
> Design: [design.md](./design.md) · Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

This spec has no migrations, models or policies. Its order is **runtime → legacy clean-out → upgrade → tooling → `Shared` → CI**:

- **Legacy deletion comes before the Laravel 13 upgrade.** Then the upgrade, Pint and Larastan only meet code that stays.
- **Each task is tests first, then code.** `php artisan test`, Pint and Larastan must pass from T5 on, once they are installed.
- **Diff size:** each task stays under ~200 lines of hand-written diff. Deleted files and `composer.lock` are not counted.

Branch: `feat/001-foundations`.

- [x] **T1 — Docker and MySQL 8.4**
  - Fulfils: REQ-1.1, 1.4, 1.5, 2.1–2.3, 2.6, 2.8, 2.9, 2.11, 3.2, 11.3, 11.5
  - Files:
    - `Dockerfile.dev`: `php:8.4.x-fpm-alpine` (newest 8.4 patch ≥ 8.4.27, pinned), `composer:2.11.x` pinned, `install-php-extensions` pinned (`intl opcache pcntl pdo_mysql zip`), dev dependencies installed, no `cp .env.example .env`, no redis.
    - `docker/php/app.ini`: `zend.exception_ignore_args=On`, `expose_php=Off`.
    - `docker-compose.yml`:
      - `mysql:8.4.x` pinned, with strict `sql_mode`, `utf8mb4` and UTC in `command:`;
      - `:?` secrets and a `mysqladmin ping` health check;
      - ports on `127.0.0.1` only, phpMyAdmin labelled local-only;
      - `redis-network` and `mysql-network` removed;
      - the app waits for `service_healthy`.
    - `docker/mysql/init/01-test-database.sh`: creates `${DB_TEST_DATABASE}` and grants on it only.
  - Verify (manual, output recorded in the verification report):
    - `docker compose down -v && docker compose up -d --build`.
    - `php -v` in the app container shows ≥ 8.4.27.
    - `SHOW GRANTS FOR 'expenses'@'%'` lists only `expenses_db` and `expenses_test`, with no `GRANT OPTION`.
    - `SELECT @@sql_mode, @@time_zone, @@character_set_server` shows the strict flags, `+00:00` and `utf8mb4`.
    - `docker compose ps` shows MySQL bound to `127.0.0.1`.

- [x] **T2 — `.env` / `.env.example`**
  - Fulfils: REQ-3.3, 11.1, 11.2
  - Files: `.env.example` (tracked) and the local `.env` (not tracked).
    - Keys added: `DB_TEST_DATABASE`, `DB_ROOT_PASSWORD`, `DB_FORWARD_PORT`.
    - `DB_USERNAME=expenses`.
    - Empty `APP_KEY`, `DB_PASSWORD` and `DB_ROOT_PASSWORD` in the example.
    - `REDIS_*` and `MEMCACHED_HOST` removed.
    - In the local `.env`: a new `APP_KEY` and new passwords.
  - Verify: a script compares the key sets of `.env` and `.env.example` (no diff). `.env.example` holds no `APP_KEY` value and no password.
  - Done together with T1 in practice, because compose reads these keys. They stay separate tasks to keep the review readable.

- [x] **T3 — Delete legacy code**
  - Fulfils: REQ-3.1, 4.2, 12.1, 12.2 (first half)
  - Files deleted:
    - `app/Http`, `app/Models`, `app/Repositories`, `app/Services`, `app/Pagination`;
    - the three legacy migrations;
    - `database/seeders/*` and `database/factories/UserFactory.php`;
    - `tests/Unit` and `tests/Feature`.
  - Files changed:
    - `routes/api.php` is emptied (health arrives in T10).
    - `bootstrap/app.php` loses the `redis.auth`/`query.user` aliases and the legacy validation renderer.
    - `config/auth.php` points to `App\Modules\Identity\Models\User::class` without `env('AUTH_MODEL')`.
  - Verify:
    - `grep -rnE "App\\\\(Http|Models|Repositories|Services|Pagination)" bootstrap config database routes tests` finds nothing.
    - `php artisan route:list` boots.

- [x] **T4 — Laravel 13, PHP `~8.4.27`, Composer and config clean-up**
  - Fulfils: REQ-1.2, 1.3, 1.6, 1.7, 2.6, 2.8, 3.4, 3.5, 4.3, 4.7
  - Files:
    - `composer.json` (`require`, `require-dev`, scripts per design) and `composer.lock`.
    - The Laravel 12 → 13 upgrade guide steps in `bootstrap/` and `config/`.
    - `config/cache.php`, `queue.php`, `session.php` and `database.php` lose their redis, memcached, dynamodb and sqs blocks.
    - `config/database.php`: mysql `timezone => '+00:00'`.
    - `bootstrap/app.php` drops `health: '/up'` and sets `apiPrefix: 'api/v1'`.
    - Deleted: `.php-cs-fixer.dist.php`, `.php-cs-fixer.cache`.
  - Verify:
    - `composer install` succeeds in the container.
    - `php artisan about` shows Laravel 13.x and PHP ≥ 8.4.27.
    - `php artisan route:list --path=api` succeeds.
    - `composer audit` is clean.

- [x] **T5 — Test harness on MySQL, Pint, Larastan**
  - Fulfils: REQ-2.4, 2.5, 2.7, 4.1, 4.4, 4.5, 4.6
  - Files:
    - `phpunit.xml`: the `Architecture`, `Shared` and `Modules` suites, forced env, no sqlite.
    - `tests/Pest.php` and `tests/TestCase.php`, with the `_test` guard.
    - `app/Shared/Tests/Feature/MysqlStrictModeTest.php`.
    - `pint.json` and `phpstan.neon`.
    - Pint run on all code.
    - `AppServiceProvider` made `final` with strict types.
    - Kept migrations get strict types.
  - Tests first:
    - `REQ-2.7 rejects an overlong string / an invalid date / an out-of-range integer`.
    - `REQ-2.5 refuses to boot tests on a database not ending in _test`.
  - Verify:
    - `php artisan test`, `pint --test` and `phpstan analyse` are all green.
    - A deliberately misformatted file makes `pint --test` exit non-zero (REQ-4.4); the file is not committed.

- [x] **T6 — Architecture tests and repository guards**
  - Fulfils: REQ-3.6, 5.1–5.11
  - Files: `tests/Architecture/Support/ModuleMap.php`, plus `LayoutTest`, `ModuleDependenciesTest`, `ModuleModelsTest`, `NamingTest`, `ActionsTest`, `StrictnessTest` and `RepositoryGuardsTest`.
  - Verify:
    - The suite is green.
    - **Proof (REQ-5.11):** for each rule, a throwaway violation makes it fail. Examples: `app/Http/X.php`, `app/Modules/Household` using `Ledger`, `FooRepository`, a non-final action, `app/Modules/X/Http`, a missing `strict_types`, `env()` in `app/`, `redis` in a config file. The failing output is summarized under **Deviations / proof** below. None of these violations is committed.

- [x] **T7 — `Money`, `Currency`, `MoneyException`**
  - Fulfils: REQ-8.1–8.10
  - Files: `app/Shared/Money/{Currency,Money,MoneyException}.php` and `app/Shared/Tests/Unit/{CurrencyTest,MoneyTest}.php`.
  - Tests first, named after the REQ:
    - exponents: TND 3, EUR 2, JPY 0;
    - `tnd` → `TND`;
    - rejects `XYZ` and `EURO`;
    - `TypeError` on a float or a numeric string;
    - zero and negative amounts allowed;
    - exact `plus` and `minus`;
    - mixed currency throws;
    - `PHP_INT_MAX` overflow throws;
    - `equals`, `compareTo`, `greaterThan`, `lessThan`, `isZero`, `isNegative`, `isPositive`.

- [x] **T8 — Error envelope: `ApiException`, `ValidationCodes`, `ApiErrorRenderer`**
  - Fulfils: REQ-6.1–6.5, 6.7, 6.8, 7.4
  - Files:
    - `app/Shared/Errors/{ApiException,ValidationCodes,ApiErrorRenderer}.php`;
    - the `withExceptions` wiring in `bootstrap/app.php`;
    - `app/Shared/Tests/Unit/ValidationCodesTest.php` and `app/Shared/Tests/Feature/ErrorRenderingTest.php`.
  - Tests first:
    - every row of the REQ-6.4 table, with routes registered in the test only;
    - nested `items.0.amount` → `items.amount.required`;
    - a rule object → snake-case class basename;
    - de-duplicated codes;
    - `retry_after_seconds` and the `Retry-After` header;
    - a 500 with `APP_DEBUG=false` has no trace, message, class, SQL or path;
    - a 500 with `APP_DEBUG=true` has `debug`;
    - an `Accept: text/html` request to `/api` still gets JSON;
    - a non-`/api` path keeps the default rendering.

- [x] **T9 — `RequireJson` and `AssignRequestId`**
  - Fulfils: REQ-6.6, 7.1–7.5
  - Files:
    - `app/Shared/Http/{RequireJson,AssignRequestId}.php`;
    - the middleware wiring;
    - the `QueryException` report callback;
    - `app/Shared/Tests/Feature/{RequireJsonTest,RequestIdTest}.php`.
  - Tests first:
    - a form body, a text body, invalid JSON, and a JSON `"abc"` or `42` each get 415;
    - a GET or DELETE with any `Content-Type` passes;
    - a JSON object or array passes;
    - success, 404 and 500 responses all carry `X-Request-Id`;
    - an incoming `X-Request-Id` is ignored;
    - a logged 5xx carries `request_id` and no request body (checked with `Log::spy`).

- [x] **T10 — Health endpoint and legacy 404s**
  - Fulfils: REQ-9.1–9.7, 12.3
  - Files:
    - `app/Shared/Health/{CheckHealth,HealthController,HealthResource,HealthThrottle}.php`;
    - `routes/api.php`;
    - `app/Shared/Tests/Feature/HealthTest.php`.
  - Tests first:
    - 200 `{"data":{"status":"ok"}}` with no extra keys;
    - DB down gives 503 `health.database_unavailable`;
    - request 61 gives 429 with `retry_after_seconds`, and the limiter uses the `file` store, with no DB query;
    - POST, PUT, PATCH and DELETE give 405;
    - `GET /up` gives 404;
    - `GET /api/v1/categories` and `POST /api/v1/money-transition` give 404 `request.not_found`.

- [x] **T11 — CI workflow**
  - Fulfils: REQ-10.1–10.4
  - Files: `.github/workflows/ci.yml`, with actions pinned to a commit SHA, the MySQL 8.4 service, the PHP version guard, and Pint, Larastan, audit and tests.
  - Verify:
    - `actionlint` passes, if available in the container; otherwise the file is reviewed against the design.
    - The first real run happens on the PR, which the owner opens or asks for.

- [x] **T12 — Docs and final verification**
  - Fulfils: REQ-11.4, 12.2 (second half), 12.4
  - Files:
    - `CLAUDE.md`: the UUID column rule and the named-rule-classes rule.
    - This file's verification report.
  - Verify:
    - `php artisan migrate:fresh` on an empty `expenses_db`.
    - The full gate: test, pint `--test`, phpstan and audit.
    - The REQ-3.6 grep finds nothing.
    - The verification report is filled in for every REQ.

## Deviations from spec

All of these were made during implementation on 2026-11-13 and are **awaiting owner approval** in the PR review. None changes a requirement.

| # | Design said | Implemented | Why |
|---|---|---|---|
| D1 | `docker/mysql/init/01-test-database.sh`, bind-mounted into `/docker-entrypoint-initdb.d` | An inline Compose `configs:` entry (`mysql-test-database`) that is copied into the container as `01-test-database.sql` | Docker Desktop refused the bind mount of that folder. Inline config needs no file sharing, no shell script, and no exec bit or CRLF concerns. Same effect: creates `${DB_TEST_DATABASE}` on a new volume and grants on it only. |
| D2 | Grant `ALL PRIVILEGES ON \`${DB_TEST_DATABASE}\`.*` | The grant escapes `_` and `%` (`expenses\_test`) through a prepared statement | `_` is a wildcard in MySQL grants. The unescaped grant also matched databases like `expensesXtest`. The official image escapes `MYSQL_DATABASE` the same way. |
| D3 | Only `DB_FORWARD_PORT` is configurable | `APP_FORWARD_PORT` and `PMA_FORWARD_PORT` too, and nginx is bound to `127.0.0.1` as well | Shop Keeper already uses host ports 8000/8080 on this machine. The defaults stay 8000/8080; the local `.env` uses 8001/8081. |
| D4 | — | `.dockerignore` added (`.git`, `.env*` except the example, `vendor`, `node_modules`, caches) | Keeps secrets out of the image (REQ-11.3's intent) and stops the host `vendor/` overwriting the one installed in the image. |
| D5 | — | Images pinned beyond the design list: `nginx:1.30.6-alpine`, `phpmyadmin:5.2.2`, `mlocati/php-extension-installer:2.13.2`. Generic `container_name`s (`mysql`, `phpmyadmin`) removed. | Reproducible builds, and no name clash with other projects' containers. |
| D6 | Composer `audit` script | No script; `composer audit` is the built-in command | Composer refuses a script that shadows a built-in command. REQ-4.7 is met by the built-in. |
| D7 | `Currency` is `final readonly` | `final` with `readonly` promoted properties | PHP forbids static properties in readonly classes, and the design memoises currencies in a static array. Immutability is unchanged. |
| D8 | ICU exponent from `ResourceBundle::create('supplementalData', 'ICUDATA', false)` | Same lookup on the `ICUDATA-curr` bundle; the entry is read as an array | On ICU 78.1, `CurrencyMeta` lives in `ICUDATA-curr`, and its int vectors come back as PHP arrays. |
| D9 | `ValidationCodesTest` under `Tests/Unit` | Under `Tests/Feature` | It needs Laravel's validator, so it boots the app. |
| D10 | Strict-mode test only | `MysqlStrictModeTest` also asserts REQ-2.4 (driver and database name), REQ-2.6 (session `sql_mode` flags) and REQ-2.8 (time zone, charset) | Cheap and catches regressions that the three write tests would not. |
| D11 | — | `tests/Support/TestDatabaseGuard` holds the `_test` check, which `TestCase` calls | Lets REQ-2.5 be unit-tested without booting a misconfigured app. |
| D12 | — | `phpunit.xml` also forces `APP_DEBUG=false` | A local `.env` with `APP_DEBUG=true` otherwise leaked debug bodies into tests. Debug-mode tests turn it on themselves. |
| D13 | Arch tests use Pest `arch()` | REQ-5.5, the "extends a forbidden class" half of REQ-5.6, and REQ-5.9's file-level strict-types check are source/reflection scans (`tests/Architecture/Support`). REQ-5.9 also scans every file under `app/`, not only namespaced classes. | The fallback the design allows in its risks section. `arch()` cannot exempt one class (5.5), does not walk the parent chain (5.6), and skips files with no namespace, such as Pest test files (5.9). |
| D14 | — | CLAUDE.md "Current state" rewritten for the post-001 codebase, plus a rule to throw `ApiException` instead of `abort()` | The old text described the prototype and Laravel 12. The `abort()` rule restates the design's risk note where future specs will read it. |
| D15 | Tests written first, task by task | Tests were written before each task's code, but the first run happened only after T4 | The Docker image took ~20 minutes to build (slow Alpine mirror). Code for T5–T10 was written meanwhile. The first run had 23 failures, all fixed (see the verification report). |

### Proof of the architecture rules (REQ-5.11)

On 2026-11-13, throwaway violations were added, the `Architecture` suite was run, and then the violations were deleted (none committed). Each rule failed on its violation:

| Violation | Failing test and message |
|---|---|
| `app/Http/LegacyProbe.php` | `LayoutTest` REQ-5.2: `+ 'Http'` |
| `app/Modules/Ledger/Http/ProbeController.php` | `LayoutTest` REQ-5.8: `+ 'Ledger'` |
| `App\Shared\Probe\SharedUsesModule` uses `App\Modules\Ledger\Models\Category` | REQ-5.3: "Expecting 'App\Shared' not to use 'App\Modules'" |
| `Identity\Actions\UsesHousehold` uses `Household\Actions\CreateHousehold` | REQ-5.3/5.4: "Expecting 'App\Modules\Identity' not to use 'App\Modules\Household'" |
| `Household\Actions\UsesLedger` uses `Ledger\Models\Category` | REQ-5.4: "Expecting 'App\Modules\Household' not to use 'App\Modules\Ledger'". REQ-5.5: "UsesLedger.php uses App\Modules\Ledger\Models\Category" |
| `ProbeRepository`, `ProbeService`, `BaseProbe` | REQ-5.6: "not to have suffix [Repository]" / "[Service]" / "prefix [Base]" |
| `ChildOfBase extends BaseProbe` | REQ-5.6: "App\Shared\Probe\ChildOfBase extends App\Shared\Probe\BaseProbe" |
| `Ledger\Actions\RecordProbe`, not final, with public `extra()` | REQ-5.7: "to be final" and "not to have public methods besides [__construct], [handle]" |
| `NoStrictTypes.php` without `declare(strict_types=1)` | REQ-5.9: "to use strict types", plus the file scan |
| `NotFinal`, `BaseProbe` (abstract) | REQ-5.9: the final checks list them |
| `ReadsEnv` calls `env('PROBE')` | REQ-5.10: "Expecting 'env' not to be used in 'App'", plus the file scan |
| `config/probe.php` contains `'redis'` | REQ-3.6: `+ 'config/probe.php'` |
| A misformatted `app/Shared/Badly.php` | REQ-4.4: `pint --test` exit code 1 |

Pest's `not->toUse()` ignores references to classes that do not exist. The REQ-5.3/5.4 rules only failed once the used classes were real. Larastan reports unknown classes, so the gate as a whole still catches this.

## Verification report

Gate on 2026-11-13, in the app container:
- `pint --test`: PASS (53 files)
- `phpstan analyse` (level 6, `app/`): no errors
- `composer audit`: no advisories
- `php artisan test`: 134 passed (415 assertions)

| REQ | Verified how | Result |
|---|---|---|
| 1.1, 1.4 | `Dockerfile.dev`: `php:8.4.27-fpm-alpine`, `composer:2.11.2`; `php -v` in the container gives 8.4.27 | ✅ |
| 1.2 | `composer.json`: `php ~8.4.27`, `laravel/framework ^13.17`, `sanctum ^4.3`, `tinker ^3.0`; `composer validate` | ✅ |
| 1.3 | `composer why-not php 8.4.26` gives "requires php (~8.4.27)"; no platform override | ✅ |
| 1.5 | `vendor/bin/{pest,pint,phpstan}` present in the image | ✅ |
| 1.6 | `php artisan about`: Laravel 13.42.0, PHP 8.4.27 | ✅ |
| 1.7 | `config/` taken from the Laravel 13 skeleton and re-customised; `route:list --path=api` succeeds | ✅ |
| 2.1 | `mysql:8.4.13` in compose and CI | ✅ |
| 2.2, 2.3 | Fresh volume; `SHOW GRANTS`: `USAGE ON *.*`, `ALL ON expenses\_db.*`, `ALL ON expenses\_test.*`, no `GRANT OPTION` | ✅ |
| 2.4 | `MysqlStrictModeTest` REQ-2.4 (driver `mysql`, database `expenses_test`) | ✅ |
| 2.5 | `TestDatabaseGuardTest` (refuses `expenses_db`, `expenses_test_copy`, `''`) | ✅ |
| 2.6 | Server `@@GLOBAL.sql_mode` has all flags; `MysqlStrictModeTest` REQ-2.6 checks the session; MySQL 8.4.13's default `sql_mode` (CI) has all flags | ✅ |
| 2.7 | `MysqlStrictModeTest`: overlong string, `2026-02-30`, `TINYINT` 128 each throw `QueryException` | ✅ |
| 2.8 | `MysqlStrictModeTest` REQ-2.8; server `+00:00` / `utf8mb4` | ✅ |
| 2.9 | `docker compose ps`: MySQL `127.0.0.1:3306`, phpMyAdmin `127.0.0.1:8081` with label `scope=local-only` | ✅ |
| 2.10 | Convention recorded in CLAUDE.md (first used by spec 002) | ✅ (convention) |
| 2.11 | Compose has the project network only | ✅ |
| 3.1–3.3 | Middlewares and aliases deleted; no `redis-network`, no `phpredis`; no `REDIS_*` / `MEMCACHED_*` in `.env` or `.env.example` | ✅ |
| 3.4 | `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` = `database`; `cache`/`jobs` migrations kept | ✅ |
| 3.5 | Blocks and comments removed from `cache`, `queue`, `session`, `database` config | ✅ |
| 3.6 | `RepositoryGuardsTest` REQ-3.6 over code and config. A repo-wide grep also matches only docs (CLAUDE.md, specs), `composer.lock` package metadata, generated `bootstrap/cache`, and the guard test itself. | ✅ |
| 4.1 | `phpunit.xml` suites `Architecture`, `Shared`, `Modules`; `php artisan test` runs all three | ✅ |
| 4.2 | `tests/Unit`, `tests/Feature` deleted | ✅ |
| 4.3–4.5 | `pint.json` (laravel + `declare_strict_types` + `final_class`); php-cs-fixer removed; `pint --test` exits 1 on a bad file | ✅ |
| 4.6 | `phpstan.neon` level 6 on `app/`, no ignores, no baseline; no errors | ✅ |
| 4.7 | `composer test`, `lint`, `lint:check`, `analyse`; built-in `composer audit` | ✅ (see D6) |
| 5.1–5.10 | `tests/Architecture/*` green; proof above | ✅ |
| 5.11 | Proof table above | ✅ |
| 6.1–6.5, 6.7 | `ErrorRenderingTest`, `ValidationCodesTest` | ✅ |
| 6.6 | `RequestIdTest` (header on 200/404/500, unique, client value ignored, log has `request_id`, no body or SQL in logs); `zend.exception_ignore_args=On` | ✅ |
| 6.8 | `ErrorRenderingTest` REQ-6.8; `GET /up` through nginx returns Laravel's HTML 404 | ✅ |
| 7.1–7.5 | `RequireJsonTest`; `ErrorRenderingTest` REQ-7.4 | ✅ |
| 8.1–8.10 | `MoneyTest`, `CurrencyTest` | ✅ |
| 9.1–9.7 | `HealthTest`; checked by hand through nginx on `127.0.0.1:8001` | ✅ |
| 10.1–10.4 | `.github/workflows/ci.yml`; `actionlint 1.7.12` clean; actions pinned to SHAs; throwaway CI credentials only | ✅ static. First real run on the PR. |
| 11.1 | Key sets of `.env` and `.env.example` identical (script diff); example has no `APP_KEY` or passwords | ✅ |
| 11.2 | Local `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` regenerated; MySQL volume recreated | ✅ |
| 11.3 | `cp .env.example .env` removed; `.dockerignore` excludes `.env` | ✅ |
| 11.4 | CLAUDE.md updated (D14 and the design's two additions) | ✅ |
| 11.5 | Stack rebuilt from scratch with `down -v` + `up -d --build` | ✅ |
| 12.1, 12.2 | Legacy deleted; no `App\(Http\|Models\|Repositories\|Services\|Pagination)` reference left; `migrate:fresh` succeeds on `expenses_db` | ✅ |
| 12.3 | `HealthTest` REQ-12.3 | ✅ |
| 12.4 | Gate green on all remaining code | ✅ |
| 12.5 | Nothing restored from git | ✅ |
