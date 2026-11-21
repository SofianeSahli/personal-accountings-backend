# 003 — Books and households: Design

> Status: Approved (2026-11-20)
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Two new modules. `Household` owns households and memberships. `Ledger` starts with one table, `books`, plus the access rule that every later money route uses. `Identity` gains one read-only action. Dependencies: `Ledger` → `Household` → `Identity`, and all three may use `Shared`.

## Key decisions

| # | Decision | Why |
|---|---|---|
| K1 | Ordered UUIDs (`HasUuids`, `CHAR(36)` ascii) on every new table and FK | CLAUDE.md "Keys" |
| K2 | One household per user: a stored generated column `household_user.active_user_id` = `user_id` when `deleted_at IS NULL`, else `NULL`, with a **unique** index. The database refuses a second live membership, also under concurrent requests, while deleted memberships never block a user | REQ-2.2, REQ-2.10, REQ-8.6 (D9) |
| K3 | One book per household: a stored generated column `household_owner_id` = `owner_id` when `owner_type = 'household'` and `deleted_at IS NULL`, else `NULL`, with a **unique** index (MySQL allows many NULLs). Holds under concurrent requests | REQ-2.3 (D10) |
| K4 | `books` has a polymorphic owner: `owner_type` + `owner_id`, both **NOT NULL**. A book cannot exist without an owner. The morph aliases are `account` and `household` (`Relation::enforceMorphMap`), the same words as `owner.type` in the API. `LedgerServiceProvider` registers `account` ⇒ `User`; `HouseholdServiceProvider` registers `household` ⇒ `Household`, so `Ledger` never names `Household`'s model (arch test REQ-5.5 of spec 001) | REQ-1.1, REQ-2.3 (D2, D14) |
| K15 | Morph columns allow no FK, so integrity comes from **soft deletes**: `books`, `households` and `household_user` have `deleted_at` (`SoftDeletes`) and are never hard-deleted, so an owner a book points to always exists. Soft-deleted rows are invisible: not listed, `404` by id, not counted in limits. The only delete endpoint is `DELETE households/{household}` | D14, REQ-2.9–2.12, REQ-8.9 |
| K16 | Deleting a household: `DeleteHousehold` soft-deletes the household and its memberships in one `DB::transaction` and dispatches `HouseholdDeleted` (`householdId`) after commit. `Ledger`'s listener `SoftDeleteHouseholdBook` calls the action `DeleteHouseholdBook`, synchronously. If it fails, the error is logged (ids only) and the `204` stands: the book is already unreachable (REQ-4.2) | REQ-2.9–2.11 |
| K5 | The 5 personal books limit is counted and inserted under `Cache::lock("ledger:personal-books:{userId}")` (database store, `cache_locks` table). Ledger cannot lock the `users` row (CLAUDE.md "Other modules' data") | REQ-1.3 |
| K6 | `Household` tells `Ledger` about memberships through the action `FindMembership` and a `Support/Membership` DTO (`householdId`, `isOwner`). Ledger never sees `HouseholdRole` (a `Models` enum, arch test REQ-5.5 of spec 001) | REQ-9.3 |
| K7 | Policies deny with explicit codes: `Response::denyAsNotFound(code: 'book.not_found')`, `Response::deny(code: 'book.forbidden')`. `ApiErrorRenderer` uses the denial's code when it is a string, else keeps `request.not_found` / `auth.forbidden` as today | REQ-8.3, REQ-8.4, CLAUDE.md "Errors" |
| K8 | `{household}` and `{book}` are **not** bound implicitly. `RequireHousehold` / `RequireBook` read the raw id: a malformed id, an unknown id and a forbidden id all go through the same `view` policy denial, so the body is the same in all three cases | REQ-8.3 |
| K9 | Owner-only `PATCH` routes authorize in the Form Request's `authorize()` (calling `Gate::authorize`), which Laravel runs before the rules, so `403` comes before `422` | REQ-8.5 |
| K10 | Field codes need rule class names (spec 001, REQ-6.3): `SupportedCurrency` gives `currency.supported_currency`, `IanaTimezone` gives `timezone.iana_timezone`. Requirements updated to match | REQ-0.2, REQ-0.3 |
| K11 | `household_id.not_found` is thrown by `CreateBook` as `ApiException('validation.failed', 422, fields: …)`. A malformed id passes the rules (`nullable`, `string`) and fails the lookup, so all three cases give the same code | REQ-2.3 |
| K12 | One rate limiter, `creations` (10/min, keyed by user id), in `Shared` (`App\Shared\Http\CreationLimit`, registered by `AppServiceProvider`). Both modules use `throttle:creations`, so neither knows the other | REQ-2.8 |
| K13 | `CurrentHousehold` lives in `Household/Support` and `CurrentBook` in `Ledger/Support`, not `Shared`: `Shared` may not know module models. CLAUDE.md is updated to say so | REQ-8.2 |
| K14 | Lists use Laravel's cursor paginator (25 per page, `?cursor=`), with the resource collection's `data`, `links` and `meta`. This is the first list endpoint, so `contract.md` fixes the shape for later specs | REQ-2.5, REQ-3.1, REQ-7.1 |

## Placement

| Piece | Path |
|---|---|
| `FindAccountNames` | `app/Modules/Identity/Actions/Accounts/` |
| Household: `CreateHousehold`, `RenameHousehold`, `DeleteHousehold`, `ListHouseholds`, `ListMembers`, `FindMembership` | `app/Modules/Household/Actions/` |
| `Household`, `HouseholdMember` (pivot model of `household_user`), enum `HouseholdRole` (`Owner`, `Member`) | `app/Modules/Household/Models/` |
| `Membership`, `CurrentHousehold`, `MemberView` | `app/Modules/Household/Support/` |
| `HouseholdController`, `HouseholdMemberController`; `CreateHouseholdRequest`, `UpdateHouseholdRequest`; `HouseholdResource`, `MemberResource`; `Middleware/RequireHousehold` | `app/Modules/Household/Controllers/…` |
| `HouseholdPolicy` (`view`, `update`, `delete`) | `app/Modules/Household/Policies/` |
| `HouseholdDeleted` | `app/Modules/Household/Events/` |
| Ledger: `CreateBook`, `UpdateBook`, `ListBooks`, `DeleteHouseholdBook` | `app/Modules/Ledger/Actions/` |
| `Book` | `app/Modules/Ledger/Models/` |
| `NewBook`, `BookChanges`, `BookPage`, `CurrentBook` | `app/Modules/Ledger/Support/` |
| `SupportedCurrency`, `IanaTimezone` | `app/Modules/Ledger/Rules/` |
| `SoftDeleteHouseholdBook` (listens to `HouseholdDeleted`) | `app/Modules/Ledger/Listeners/` |
| `BookController`; `CreateBookRequest`, `UpdateBookRequest`; `BookResource`; `Middleware/RequireBook` | `app/Modules/Ledger/Controllers/…` |
| `BookPolicy` (`view`, `update`) | `app/Modules/Ledger/Policies/` |
| Migrations, factories (`HouseholdFactory`, `HouseholdMemberFactory`, `BookFactory`), `routes.php`, `HouseholdServiceProvider` (morph alias `household`), `LedgerServiceProvider` (morph alias `account`, `enforceMorphMap`) | each module's `database/…`, root; both providers in `bootstrap/providers.php` |
| `CreationLimit` (rate limiter definition) | `app/Shared/Http/` |
| Denial-code arm | `app/Shared/Errors/ApiErrorRenderer.php` |
| Tests | `app/Modules/{Household,Ledger}/Tests/` with `Tests/Support` scenario helpers; `app/Modules/Identity/Tests/FindAccountNamesTest.php` |

Cross-module calls:

- `Household` → `Identity`: `FindAccountNames` (member names); type-hints `User` as the current user.
- `Ledger` → `Household`: `FindMembership` (book access, `can_manage`, household books); listens to `HouseholdDeleted` (K16); type-hints `User`.
- `Identity` and `Household` call nothing above them. `ModuleMap::ALLOWED` already lists this direction; the existing arch tests cover the new modules as soon as their folders exist (REQ-9.1).

## Data

All UUID columns: `uuid(...)->charset('ascii')->collation('ascii_bin')`. One table per migration.

| Table | Columns | Indexes / FKs |
|---|---|---|
| `households` | `id` uuid PK; `name` varchar(60); timestamps; `deleted_at` (K15) | — |
| `household_user` | `id` uuid PK; `household_id` uuid; `user_id` uuid; `role` varchar(10) (`owner`/`member`); `active_user_id` uuid null, stored generated (K2); timestamps (`created_at` = date joined); `deleted_at` (K15) | **unique `active_user_id`** (K2); index `user_id`; index (`household_id`, `role`, `created_at`); FK `households` restrict; FK `users` restrict |
| `books` | `id` uuid PK; `name` varchar(60); `currency` char(3) ascii; `timezone` varchar(64); `owner_type` varchar(20) ascii **not null**; `owner_id` uuid **not null**; `household_owner_id` uuid null, stored generated (K3); timestamps; `deleted_at` (K15) | **unique `household_owner_id`** (K3); index (`owner_type`, `owner_id`, `created_at`); `CHECK (owner_type IN ('account', 'household'))`; no FK (morph, K4/K15) |

Migrations: `Household/database/migrations/2026_11_20_000001_create_households_table.php`, `…000002_create_household_user_table.php`; `Ledger/database/migrations/2026_11_20_000003_create_books_table.php`. Laravel sorts module migrations by file name, so `books` runs after `households`.

The columns are written by hand, not with `uuidMorphs()`, so `owner_id` gets the ascii charset (CLAUDE.md "Keys").

No cascades and no hard deletes: households, memberships and books are soft-deleted only (K15). Deleting a book on its own and deleting accounts are out of scope.

## Actions

| Action | Input → output | Rules | Transaction / lock |
|---|---|---|---|
| `Identity\FindAccountNames` | `list<string>` ids → `array<string, string>` id ⇒ name | REQ-7.3; read only, unknown ids left out | — |
| `CreateHousehold` | `User`, `string $name` → `Household` | REQ-2.1, 2.2: household + owner membership; `UniqueConstraintViolationException` on `user_id` → `ApiException household.limit_reached 422`; a pre-check gives the same answer without an insert | `DB::transaction` |
| `RenameHousehold` | `Household`, `?string $name` → `Household` | REQ-2.7 | — |
| `DeleteHousehold` | `Household` → void | REQ-2.9, 2.10, 2.12: soft-deletes memberships, then the household; dispatches `HouseholdDeleted` after commit (K16) | `DB::transaction` |
| `ListHouseholds` | `User` → cursor page of `Household` with the caller's `role` | REQ-2.5 | — |
| `ListMembers` | `Household` → cursor page of `MemberView` (`id`, `name`, `role`) | REQ-7.1–7.3: order `role` desc (`owner` > `member`), `created_at`, `id`; names from `FindAccountNames` for the page only | — |
| `FindMembership` | `User` → `?Membership` | REQ-9.3 | — |
| `CreateBook` | `User`, `NewBook` (name, `Currency`, timezone, `?householdId`) → `Book` | Personal: REQ-1.1–1.3 under the K5 lock. Household: REQ-2.3: `FindMembership`, id mismatch or malformed → `household_id.not_found` (K11), not owner → `household.forbidden 403`, existing book or violation of the K3 unique index → `book.household_has_book 422`. Sets `owner_type`/`owner_id` from the account or the household id | lock (personal) / unique index (household) |
| `UpdateBook` | `Book`, `BookChanges` (`?name`, `?timezone`) → `Book` | REQ-5.1, 5.4: only the fields sent | — |
| `DeleteHouseholdBook` | `string $householdId` → void | REQ-2.11: soft-deletes the book owned by that household, if any; idempotent | — |
| `ListBooks` | `User` → `BookPage` (cursor page of `Book`, `?Membership`) | REQ-3.1: `(owner_type = 'account' AND owner_id = me) OR (owner_type = 'household' AND owner_id = my household)`, not soft-deleted, `created_at`, `id` | — |

`Book::canBeManagedBy(User, ?Membership): bool` is a pure method (no query), used by `BookPolicy`, `RequireBook` and `BookResource`, so `can_manage` and the policy can't disagree (REQ-3.2, 5.3).

## Endpoints

All under `/api/v1`, `auth:sanctum` (REQ-8.1). Full bodies in [contract.md](./contract.md).

| Method | Path | Middleware | Request | Response | Errors |
|---|---|---|---|---|---|
| GET | `households` | — | — | `HouseholdResource` collection | 401 |
| POST | `households` | `throttle:creations` | `CreateHouseholdRequest` | 201 `HouseholdResource` | 401, 422, 429 |
| GET | `households/{household}` | `RequireHousehold` | — | `HouseholdResource` | 401, 404 |
| PATCH | `households/{household}` | `RequireHousehold` | `UpdateHouseholdRequest` (authorize `update`) | `HouseholdResource` | 401, 404, 403, 422 |
| DELETE | `households/{household}` | `RequireHousehold` | — (authorize `delete` in the controller) | 204, no body | 401, 404, 403 |
| GET | `households/{household}/members` | `RequireHousehold` | — | `MemberResource` collection | 401, 404 |
| GET | `books` | — | — | `BookResource` collection | 401 |
| POST | `books` | `throttle:creations` | `CreateBookRequest` | 201 `BookResource` | 401, 422, 403, 429 |
| GET | `books/{book}` | `RequireBook` | — | `BookResource` | 401, 404 |
| PATCH | `books/{book}` | `RequireBook` | `UpdateBookRequest` (authorize `update`) | `BookResource` | 401, 404, 403, 422 |

Request rules:

- `name`: `required|string|max:60` on create; `sometimes|required|string|max:60` on update. Laravel's `TrimStrings` and `ConvertEmptyStringsToNull` turn a blank name into `null`, which gives `name.required` (REQ-0.1).
- `currency`: `sometimes|string` + `SupportedCurrency` on create; `prohibited` on update.
- `timezone`: `sometimes|string` + `IanaTimezone` (member of `DateTimeZone::listIdentifiers()`, exact case) on create; `sometimes|required|string` + `IanaTimezone` on update.
- `household_id`: `nullable|string` on create; `prohibited` on update.

Order on `POST books` (a household referenced in the body, not the path): `401` → `429` → `422` field rules → `422 household_id.not_found` → `403 household.forbidden` → `422 book.household_has_book`. Path routes follow REQ-8.5: `401` → `404` (middleware) → `403` (Form Request `authorize`) → `422`.

## Authorization

- `RequireHousehold`: loads the household by raw id (none if malformed), `Gate::authorize('view', …)`, binds `CurrentHousehold` (household + caller's `HouseholdRole`) in the container.
- `HouseholdPolicy::view`: caller has a `household_user` row → allow, else `denyAsNotFound(code: 'household.not_found')`, also for a `null` household. `update` and `delete`: `view`, then role `owner`, else `deny(code: 'household.forbidden')`.
- `RequireBook`: same pattern, binds `CurrentBook` (book + `canManage`).
- `BookPolicy::view`: owner is `account` and `owner_id = caller`, or owner is `household` and `FindMembership` gives `owner_id`, else `denyAsNotFound(code: 'book.not_found')` (REQ-4.2, read at each request). `update`: `view`, then `canBeManagedBy`, else `deny(code: 'book.forbidden')`.
- Policies are registered in each provider with `Gate::policy`.

## Error codes

New: `household.not_found` 404, `household.forbidden` 403, `household.limit_reached` 422, `book.not_found` 404, `book.forbidden` 403, `book.limit_reached` 422, `book.household_has_book` 422. Field codes: `household_id.not_found`, `household_id.prohibited`, `currency.supported_currency`, `currency.prohibited`, `timezone.iana_timezone`.

The renderer change (K7) keeps every existing answer; spec 001's `contract.md` gains one line saying that policies may name their code.

## Docs

- `CLAUDE.md` (REQ-9.8): module table (`Household`: households, household_user; `Ledger`: books, then categories, transactions, …); "Data" → book scoping (`book_id`, `/books/{book}/…`, `RequireBook`, `CurrentBook`); `CurrentHousehold`/`CurrentBook` in their modules (K13); currency and timezone on the book; "Deleting" → books and households are soft-deleted too, and a book's polymorphic owner is never empty (D14); "Seeding" → D13; Identity's table list unchanged.
- `README.md`: status line and modules line.
- Postman (REQ-9.7): folder `Books and households (spec 003)`, 10 requests; the test scripts of `POST households` and `POST books` store `household_id` and `book_id`. New environment variables: `household_id`, `book_id`, `household_name`, `book_name`, `currency`, `timezone`.

## Requirement coverage

| REQ | Covered by |
|---|---|
| 0.1–0.4 | Form Requests, `SupportedCurrency`, `IanaTimezone`; validation tests in `CreateHouseholdTest`, `CreateBookTest`, `UpdateBookTest` |
| 1.1–1.3 | `CreateBook` (personal, K5); `CreateBookTest` |
| 1.4 | No listener anywhere; `NoAutomaticCreationTest` (sign-up and Google sign-in leave `households` and `books` empty) |
| 2.1–2.2, 2.4–2.7 | `CreateHousehold` (K2), `ListHouseholds`, `RenameHousehold`, `HouseholdResource`; `CreateHouseholdTest`, `ListHouseholdsTest`, `ShowHouseholdTest`, `UpdateHouseholdTest` |
| 2.3 | `CreateBook` (household, K3, K11); `CreateBookTest` |
| 2.8 | `CreationLimit` (K12); throttle tests, one counting across both routes |
| 2.9–2.12 | `DeleteHousehold`, `HouseholdDeleted`, `SoftDeleteHouseholdBook`, `DeleteHouseholdBook` (K2, K16); `DeleteHouseholdTest` (204, member 403, former members can create again, book 404 and unlisted, rows still present with `deleted_at`, book unreachable when the listener fails) |
| 3.1–3.2 | `ListBooks`, `BookResource`, `Book::canBeManagedBy`; `ListBooksTest` |
| 4.1–4.2 | `RequireBook`, `BookPolicy::view`; `ShowBookTest` |
| 5.1–5.4 | `UpdateBook`, `UpdateBookRequest`, `BookPolicy::update`; `UpdateBookTest` |
| 7.1–7.3 | `ListMembers`, `FindAccountNames`, `MemberResource`; `MembersTest`, `FindAccountNamesTest` |
| 8.1–8.7 | Route groups, middleware (K8), policies (K7, K9), unique indexes; `AccessTest` per module (malformed / unknown / foreign id give byte-equal bodies) |
| 8.8–8.9 | K4, K15 (NOT NULL morph columns, `CHECK`, `SoftDeletes`); `BookIntegrityTest` (insert without owner refused; soft-deleted book and household give `404`, are not listed, not counted) |
| 9.1–9.3 | `ModuleMap`, existing arch tests |
| 9.4 | No logging in new code; `NoPersonalDataInLogsTest` extended to the new routes |
| 9.5–9.6 | Test files above, both modes via `Identity` test helpers (`IdentityScenario`, `WebBrowser`) |
| 9.7–9.8 | **Docs** |

## Risks / alternatives considered

- **Two nullable owner columns with FKs** (`user_id`, `household_id`, `CHECK` one set): real FKs, but a book could be stored half-linked if the check were ever dropped, and each new owner kind adds a column. Rejected by the owner for the morph pair (K4).
- **No FK on the morph pair**: the database cannot refuse an `owner_id` that points nowhere. Only the actions write it, and owners are never hard-deleted (K15). Accounts have no soft delete yet; deleting accounts will need its own spec that keeps their books valid.
- **Locking the user row for the personal-book limit**: cleanest SQL, but `Ledger` would read `Identity`'s table. The cache lock (K5) uses the `database` store already in place; if the lock cannot be taken within 5 s, `CreateBook` turns the `LockTimeoutException` into `ApiException('server.unavailable', 503)`.
- **Members ordered by `role` text**: correct for `owner`/`member` (`owner` > `member`). A third role would need an explicit rank column; noted for spec 004.
- **Two calls to set up a household** (household, then book): needed because `Household` may not call `Ledger`. A failed second call leaves a household without a book, which the app can complete later.
- **`Identity` test helpers used from other modules' tests**: allowed by the dependency direction (`Household`/`Ledger` → `Identity`).
