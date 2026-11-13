# CLAUDE.md — Household Expenses backend

Guidance for Claude Code (and humans) working in this repository.

## Project

Laravel API for a household expenses app. A household (first the owner and his wife, later any family) records its shared income, expenses and savings, and sees where the money goes.

- **Clients:** the Angular app `expenses-management` in `C:\projects\angular-frontend-toolbox`, which will later also be built with Capacitor as an Android/iOS app.
- **Release path:** private use first, then a public release. Write everything as if strangers will use it.
- **Roadmap and decisions:** https://claude.ai/artifact/Sciz8ctvnM5WVWWGuKoV7q

## Current state — read before changing anything

- Spec 001 (Foundations) deleted the first prototype (`app/Http`, `app/Models`, `app/Repositories`, `app/Services`, `app/Pagination`, its migrations, seeders and factories). Never restore it from git or copy its patterns.
  - Prototype features (profile, categories, money transitions) are rebuilt from scratch by specs 002–005.
- `app/` holds only `Modules`, `Shared` and `Providers`, and every rule below (architecture, Pint, Larastan) applies to all code with no exception.
- The API serves only `GET /api/v1/health` until spec 002. There is no `users` or `sessions` table yet, so `/sanctum/csrf-cookie` fails until Identity exists.
- There is no data to keep.

## Stack

- **Runtime:** Laravel 13 on PHP 8.4, at least 8.4.27 (fixes CVE-2026-109618/109619). Never deploy on PHP 8.2 or older.
- **Data:** MySQL 8.4 LTS in every environment, tests and CI included (database `expenses_test`).
  - The app connects as a dedicated user, never `root`.
  - Strict `sql_mode`, `utf8mb4` and a UTC session time zone.
- **No Redis.** Cache, rate limits, queues and sessions use Laravel's `database` drivers (`cache`, `jobs` tables; `sessions` comes with Identity). Add Redis only when a spec shows a measured need. Never re-add redis, memcached, dynamodb or sqs config blocks.
  - Production may share one MySQL server with other apps on the same VPS. This app gets its own database and its own MySQL user, with rights on that database only.
  - Keep Laravel's MySQL `strict` mode on.
  - MySQL cannot roll back schema changes, so a failed migration leaves a half-changed schema: keep one schema change per migration.
- **Auth:** Laravel Sanctum.
- **Tooling:** Pest for tests, Pint for style, Larastan (level 6 minimum) for static analysis.
  - Pint and Larastan cover all code. No excluded paths, no baseline, no ignore rules.
- **CI:** GitHub Actions runs Pint, Larastan, `composer audit` and the tests against MySQL 8.4 on every pull request and every push to `main`. A red check blocks the merge.
- **Docker:**
  - Everything runs in Docker through `docker-compose.yml`. Run PHP, Composer and Artisan inside the app container (`docker compose exec personal-accounting-backend <command>`), not on the host.
  - Any new backend service goes in `docker-compose.yml`. phpMyAdmin and published database ports are for local use only.
- **Dependencies:** install packages with `composer require` and state why in the PR. Never edit `vendor/`.

## Commands

```bash
docker compose up -d --build
docker compose exec personal-accounting-backend php artisan test --compact
docker compose exec personal-accounting-backend php artisan test --compact --filter=<name>
docker compose exec personal-accounting-backend vendor/bin/pint
docker compose exec personal-accounting-backend vendor/bin/phpstan analyse
docker compose exec personal-accounting-backend php artisan route:list --path=api
```

---

## Spec-Driven Development (mandatory workflow)

**Rule: no production code is written for a feature, behaviour change or non-trivial fix until its spec exists and the owner has approved it.** The spec is the source of truth.

| Change | Spec required? |
|---|---|
| New feature, endpoint, module, table or action | **Yes — full spec** |
| Change to an endpoint's request or response, or to a business rule | **Yes — full spec**, or update the existing one |
| Bug fix touching more than one file or changing observable behaviour | **Yes — lightweight spec** (requirements + tasks) |
| Typo, rename, formatting, dependency bump with no behaviour change | No — mention it in the commit message |

If unsure, ask the owner rather than skipping the spec.

### Where specs live

```
specs/
  _templates/                    copy these; never edit them for a feature
    requirements.md  design.md  tasks.md
  <NNN>-<feature-slug>/
    requirements.md  design.md  tasks.md
    contract.md                  every endpoint this spec adds or changes (request, response, error codes)
```

- **Numbering:** spec numbers follow the roadmap (001 Foundations, 002 Accounts, 003 Households, 004–005 Transactions and summary, …).
- **Features in both repos:** a feature that touches both repos has the same number in both. The API contract lives in this repo's `contract.md`, and the frontend spec links to it.

### Phases — each ends with an explicit owner approval gate

1. **Requirements**: *what* and *why*.
   - User stories, and acceptance criteria in EARS form with stable IDs (`REQ-1.1`).
   - Cover the unhappy paths: validation errors, unauthenticated, another household's data, rate limits, duplicates, date edge cases.
   - List non-goals and open questions. **Ask the owner; never invent answers.** ⛔ Stop for approval.
2. **Design**: *how*, mapped to the modules below.
   - Tables and migrations, actions, routes, policies, events, error codes, and `contract.md`.
   - Every design section cites the `REQ` IDs it covers. ⛔ Stop for approval.
3. **Tasks**: small ordered tasks (< ~200 lines of diff each), each with its `REQ` IDs and how it is verified.
   - Order: migrations/models → actions → policies → controllers/requests/resources → routes → cleanup of legacy code. ⛔ Stop for approval.
4. **Implement** one task at a time:
   1. Write the failing tests for the task's criteria first.
   2. Write the minimum code to make them pass.
   3. Run the tests, Pint and Larastan.
   4. Tick the task (`- [x]`) and note any deviation.
   - If the spec turns out wrong or incomplete, stop, update it, and get approval. Code and spec never diverge silently.

**Commits:** commit only when the owner asks. Use one feature or fix per branch. Messages reference the spec, e.g. `feat(ledger): record a transaction [spec 004, REQ-2.1]`.

---

## Architecture: modules by business domain

Code is split by business domain under `app/Modules/<Module>/`. Cross-cutting code lives in `app/Shared/`.

| Module | Owns (tables) | May depend on |
|---|---|---|
| `Identity` | users, personal_access_tokens, sessions, email_codes, password reset codes | `Shared` only |
| `Household` | households, household_user (role), household_invitations | `Identity`, `Shared` |
| `Ledger` | categories, transactions, recurring_rules; also the summaries | `Household`, `Identity`, `Shared` |

- **Dependencies point one way only: `Ledger` → `Household` → `Identity`.** `Identity` and `Shared` never use another module. This keeps modules reusable in other apps.
- **New modules:** create one only when a spec needs it (for example `Reporting`, once summaries outgrow `Ledger`). No empty folders, no speculative layers.

### Module layout

```
app/Modules/Ledger/
  Actions/                 one use case per class: RecordTransaction, DeleteTransaction, SummarizePeriod
  Controllers/             thin controllers — always Controllers/, never Http/
    Requests/              Form Requests
    Resources/             JSON resources
    Middleware/            module middleware (e.g. RequireHousehold in Household)
  Models/                  Eloquent models and enums (CategoryType, Frequency)
  Policies/
  Support/                 readonly value objects and DTOs passed to actions (NewTransaction, Period)
  Events/                  TransactionRecorded, …
  Listeners/
  Tests/                   Pest feature and unit tests of this module (+ Tests/Support for scenario helpers)
  database/migrations/
  database/factories/
  routes.php               registered under /api/v1
  LedgerServiceProvider.php   loads migrations and routes, registers policies, events and rate limiters
app/Shared/                CurrentHousehold, Money, ApiException + error renderer, RequireJson, …
```

### Module rules

- **Ownership:** a module owns its models, actions, controllers, form requests, resources, policies, events, migrations, factories, routes and tests. Its service provider is listed in `bootstrap/providers.php`.
- **Other modules' data:** a module never reads or writes another module's tables or models. It calls the other module's actions or listens to its events. Sharing ids and foreign keys is fine.
  - The one exception: any module may type-hint `Identity`'s `User` model as the authenticated user, but never queries users itself.
- **No extra layers:** no repository or service layers, and no generic base classes (`BaseRepository`, `BaseService`, `BaseController`). Eloquent is the persistence layer; actions hold the rules.
- **Enforced:** architecture tests in `tests/Architecture` (Pest `arch()`) check these rules and the dependency direction on all code under `app/`.
  - `app/` may contain only `Modules`, `Shared` and `Providers`.
  - No class anywhere may be named `*Repository`, `*Service` or `Base*`, except Laravel `ServiceProvider`s.

## Code

- **Controllers stay thin:**
  1. Validate with a Form Request.
  2. Authorize with a policy (`Gate::authorize`).
  3. Call one action.
  4. Return a JSON resource.
- **Actions:**
  - Business rules live in `final` action classes with one public method, `handle(...)`, and constructor injection.
  - Anything that writes several tables runs in a `DB::transaction` inside the action.
  - Actions take typed DTOs or models, never a `Request` or loose arrays.
  - An action is also what a scheduled job or a listener calls, so a rule is never written twice.
- **PHP:**
  - `declare(strict_types=1);` in every file; `final` classes by default; `readonly` DTOs.
  - Constructor property promotion, explicit parameter and return types, and curly braces always.
  - Enums for closed sets, with TitleCase keys.
  - No `env()` outside `config/`.
- **No comments:** code explains itself through clear names, small functions and named constants. PHPDoc only for array shapes and generics.
- **Before a task is done:** run Pint, then Larastan.

## Data

- **Household scoping:**
  - Every household-owned table has a `household_id`, and every query is scoped to the current household.
  - Household routes are nested (`/api/v1/households/{household}/transactions`). `RequireHousehold` checks membership and binds `CurrentHousehold`.
  - A user must never reach another household's data.
- **Money:** an integer (`bigint`) in the currency's minor unit (TND: millimes, 3 decimals; EUR: cents).
  - Never floats, never decimals in PHP. Use `Shared\Money`.
  - The household's currency (ISO 4217) gives the exponent, read from ICU (`ext-intl`); no currency is hard-coded.
  - The API sends amounts as integers plus a currency code.
- **Dates:**
  - A transaction date is a `date` (no time), exchanged as `YYYY-MM-DD`.
  - Timestamps are stored in UTC.
  - "This month" is computed in the household's timezone.
- **Keys:** primary keys are ordered UUIDs (`HasUuids`) stored as `CHAR(36)` (Laravel's `uuid()` column) with the `ascii` character set. Foreign keys to them use the same type. Never `BINARY(16)`.
  - Write them as `$table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin')`, and the same on every foreign-key column.
- **Deleting:**
  - Transactions are soft-deleted.
  - Deleting a category never deletes transactions. Its spec decides whether to block or re-assign.
  - No `onDelete('cascade')` from reference data to money records.
- **Seeding:** default categories are created per household by an action when the household is created, not by a global seeder.
- **Migrations:** never edited once merged; add a new migration instead.

## API

- **Format:**
  - REST under `/api/v1`, JSON only (`RequireJson`).
  - Health: `GET /api/v1/health` (Laravel's `/up` is removed).
  - A JSON resource for every response; never return raw models.
  - Cursor pagination for lists.
- **Errors:**
  - One format with a stable machine code and no user-facing text: `{"error": {"code": "transaction.not_found"}}`.
  - Validation errors: `{"error": {"code": "validation.failed", "fields": {"amount": ["amount.required"]}}}`. Field entries are codes (`<field>.<rule>`), never messages.
  - Custom validation rules are named classes (`InternationalPhone` gives `phone.international_phone`), never closures, which would all give `closure_validation_rule`.
  - Throw `ApiException` with an explicit code, never `abort()`: any HTTP status the renderer does not map becomes `500 server.error`.
  - Built in `app/Shared`, adapted from Shop Keeper's reference; never depend on Shop Keeper.
  - Every response carries an `X-Request-Id` header. 500 bodies add exception details only when `APP_DEBUG=true`.
  - The frontend translates codes (French first, English later).
- **Contract changes:** any change to an endpoint's request or response is a contract change. Update the spec's `contract.md` and the tests in the same change.

## Authentication (Identity module)

Sanctum in two modes. Both use the same user and the same actions.

- **Web (Angular SPA): cookie session.**
  - Flow: `GET /sanctum/csrf-cookie`, then `POST /api/v1/auth/login`.
  - `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` are set to the shared parent domain.
  - Cookies are `Secure`, `HttpOnly` and `SameSite=Lax`.
  - CORS allows only the configured frontend origins, with credentials.
- **Mobile (Capacitor): per-device personal access token.**
  - Flow: `POST /api/v1/auth/token` with a client-generated `device_id`.
  - One token per device, replaced on a new sign-in.
  - Tokens expire, and are revoked on logout and on password change.
- **Accounts:**
  - Sign-up by email and password with a 6-digit emailed code; password reset by emailed code.
  - Google sign-in and two-step sign-in may come later, each by its own spec.
  - `C:\shop-keeper\shop-keeper-backend\app\Modules\Identity` is a reference implementation of these flows. Port and adapt what a spec needs; never depend on it.
- **Safeguards:**
  - Sign-in, sign-up, reset and email-sending routes are rate limited.
  - Messages are generic, so it never reveals whether an account exists.

## Authorization

- Policies on every route that touches household data: membership first, then role (`owner` / `member`) where the spec says so.
- UUIDs are not access control.

## Tests

- **Placement:** module tests live in `app/Modules/<Module>/Tests`; architecture tests in `tests/Architecture`.
- **Every endpoint** has feature tests for:
  - success
  - validation errors
  - unauthenticated access (401)
  - access to another household's data
  - Auth endpoints are tested in both modes: cookie and token.
- **Every action with a business rule** has tests for that rule. Test names start with the requirement: `it('REQ-2.3 refuses a member of another household', …)`.
- **Data:** use factories, never seeders. Assert money as exact integers.
- **Done:** `php artisan test`, Pint and Larastan must pass before a task is done. Never delete a test without the owner's approval.

## Security

- **Secrets:**
  - Secrets and environment values stay in `.env`, never in code or git.
  - `.env.example` mirrors `.env`: same keys, safe values, empty secrets. A key added to `.env` goes into `.env.example` in the same change.
- **Hashing:** store only hashes of passwords, emailed codes and invitation tokens. Invitations are single-use and expire.
- **Logging:** log no personal data — names, emails, amounts, notes, passwords, codes or tokens.
- **Production:** `APP_DEBUG=false` outside local. Errors return a generic body and are logged with a request id.
- **Proxies:** rate limits use `$request->ip()`. Behind a load balancer or reverse proxy, configure `trustProxies` before the first production deploy.
- **Dependencies:** `composer audit` runs in CI on every pull request and must be clean to merge and to release.

## Hosting

Hosting is not chosen yet. Keep the app deployable anywhere: configuration only through `.env`, no provider-specific code or services.
