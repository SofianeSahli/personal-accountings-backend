# 005 — Categories: Design

> Status: Approved (2026-11-22), when the owner asked to implement it
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Everything lives in `Ledger`. One new table, `categories`, holds the three-level tree. A PHP file in the module is the list (the "catalogue"). An Artisan command checks it and brings the table in line with it, in one transaction. One read-only route serves the whole tree with an `ETag`. No other module changes.

## Key decisions

| # | Decision | Why |
|---|---|---|
| K1 | The catalogue is a PHP file that returns a nested array: `app/Modules/Ledger/resources/categories.php`. Each node has `code` (the **full** dotted code), `emoji` (string, or `null` for a leaf), `children` (tops and groups only) and `type` (tops only). File order is display order. A PHP array is formatted by Pint, read by Larastan, needs no parser, and keeps emoji as UTF-8 | REQ-2.1, REQ-2.8 |
| K2 | The level comes from the code: 1 segment = top, 2 = group, 3 = leaf. Because a child's code must be its parent's code plus one segment, a code can never change level or parent. Only emoji, order and retirement can change | REQ-2.4, C7 |
| K3 | Every row stores its `type`, copied from its top category, so spec 006 checks an entry's type without walking up the tree. The API sends `type` on tops only (REQ-1.2) | C3, REQ-2.5 |
| K4 | Retiring sets `retired_at`. The model does **not** use `SoftDeletes`, because retired categories must still be listed and found (REQ-1.6); nothing ever deletes a row | REQ-2.2, REQ-2.6 |
| K5 | `retired` in the answer is "this row or an ancestor has `retired_at`", computed while building the answer. With K2 a parent leaving the file always takes its children with it, so this only guards against hand-edited rows | REQ-1.6 |
| K6 | `ETag` and `304` come from Laravel's `cache.headers:private;no_cache;etag` middleware on the route: a hash of the body, compared with `If-None-Match`. The tag changes exactly when the answer changes, needs no stored version, and `no-cache` makes the app revalidate on every open. It runs inside `auth:sanctum`, so `401` comes first | REQ-1.4, R4 |
| K7 | The answer is the whole tree in one response, **not** cursor-paginated: it is a fixed, global list of about 170 rows. This is the one exception to CLAUDE.md "Cursor pagination for lists", written into CLAUDE.md | REQ-1.1, R2 |
| K8 | The catalogue check is a pure value object, `CategoryCatalogue::fromArray()`, that collects **every** problem before throwing `InvalidCatalogue` (a list of strings). The type-change check (R5) needs the database, so `SyncCategories` runs it inside its transaction and throws the same exception. The command prints the problems and exits `1` | REQ-2.4, REQ-2.5 |
| K9 | The command reads the shipped file by default; `--file=<path>` reads another one. Tests use it with temporary files; the owner can use it to check a draft list | REQ-3.4 |
| K10 | The route is throttled to 60 requests a minute per account (`throttle:60,1`), like other reads would be. The app caches with the `ETag`, so normal use stays far below. Over the limit: `429 request.throttled` | Checklist (rate limits) |
| K11 | The translation files in `specs/005-categories/translations/` stay as committed (already generated from Appendix A). A test checks that each one holds exactly the catalogue's keys, each with the name from Appendix A, so they cannot drift | REQ-2.10 |

## Placement

| Piece | Path |
|---|---|
| Catalogue file | `app/Modules/Ledger/resources/categories.php` |
| `Category` model; enums `CategoryType` (`Expense`, `Income`, `Saving`), `CategoryLevel` (`Top` = 1, `Group` = 2, `Leaf` = 3) | `app/Modules/Ledger/Models/` |
| `CategoryCatalogue`, `CatalogueEntry`, `InvalidCatalogue`, `SyncReport` | `app/Modules/Ledger/Support/` |
| `SyncCategories`, `ListCategories` | `app/Modules/Ledger/Actions/` |
| `SyncCategoriesCommand` (`ledger:sync-categories`) | `app/Modules/Ledger/Commands/` (new folder, added to the layout in CLAUDE.md) |
| `CategoryController`; `CategoryResource` | `app/Modules/Ledger/Controllers/…` |
| Migration, `CategoryFactory` | `app/Modules/Ledger/database/…` |
| Route | `app/Modules/Ledger/routes.php` |
| Command registration | `LedgerServiceProvider` (`$this->commands([...])` when running in console) |
| Tests | `app/Modules/Ledger/Tests/Categories/` |

Cross-module calls: none. The route uses `auth:sanctum` and nothing from `Household`. The architecture tests stay as they are (REQ-3.5).

## Data

Two migrations, one schema change each: `app/Modules/Ledger/database/migrations/2026_11_22_000001_create_categories_table.php`, then `…000002_add_type_check_to_categories_table.php` for the `CHECK` (same pattern as `books`).

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK, ascii | `HasUuids`; spec 006 entries point to it |
| `parent_id` | uuid null, ascii | FK `categories.id`, **restrict** (no cascade); `null` for tops |
| `code` | varchar(120) ascii `ascii_bin` | **unique**; the public identifier (C7) |
| `translation_key` | varchar(140) ascii `ascii_bin` | **unique**; `categories.<code>.label`, set on insert, never changed (REQ-2.9) |
| `level` | tinyint unsigned | `CategoryLevel` |
| `type` | varchar(10) ascii | `CategoryType`; `CHECK (type IN ('expense', 'income', 'saving'))` |
| `emoji` | varchar(32) utf8mb4, null | `null` only on some leaves (C6) |
| `is_other` | boolean | true for leaves whose last segment is `other` |
| `position` | smallint unsigned | order among its siblings, from the file |
| `retired_at` | timestamp null | K4 |
| `created_at`, `updated_at` | timestamps | |

Index `(parent_id, position)` for the ordered read. No `deleted_at`.

## Catalogue rules (`CategoryCatalogue::fromArray`)

Each rule gives one problem line naming the code (or the position, if the code is missing). REQ-2.4:

1. Every node is an array with a string `code`. Unknown keys are refused.
2. Every segment matches `^[a-z][a-z0-9_]*$` and is not `label`.
3. A code has 1, 2 or 3 segments, and sits at that depth in the file (K2); a child's code is its parent's code plus one segment.
4. A code appears once in the file.
5. Tops and groups have a non-empty `children` list; leaves have no `children`.
6. Tops have `type` in `expense`, `income`, `saving`; groups and leaves have no `type`.
7. Tops and groups have a non-empty string `emoji`; a leaf's `emoji` is a non-empty string or `null`.
8. Each group has an `other` leaf, and it is the group's last leaf.

`InvalidCatalogue` carries `list<string> $problems`. Problem lines are for the operator's console only; they never reach the API.

## Actions

| Action | Input → output | Rules | Transaction |
|---|---|---|---|
| `SyncCategories` | `CategoryCatalogue` → `SyncReport` (`created`, `updated`, `retired`, `restored`, `unchanged`) | Loads every row by code (`lockForUpdate`). R5: an existing code (retired or not) with another `type` → `InvalidCatalogue`, nothing written (REQ-2.5). Then, tops → groups → leaves so parents exist: new code → insert with its `translation_key`, `level`, `type`, `is_other` (REQ-2.2, 2.9); existing code → update `emoji`/`position` if they differ; a retired code back in the file → clear `retired_at` (counted as restored, not updated); a live code missing from the file → set `retired_at`. Never deletes (REQ-2.6). A second run on the same file finds nothing to change (REQ-2.3) | `DB::transaction` |
| `ListCategories` | — → `Collection<Category>` of tops, with `children.children` eager-loaded, each level ordered by `position` | REQ-1.1, R1, R3: retired rows included | — |

`SyncCategoriesCommand`: reads the file (K9), builds the catalogue, calls `SyncCategories`, prints `Categories: N created, N updated, N retired, N restored, N unchanged.` and exits `0`. On `InvalidCatalogue`, or a file that is missing or does not return an array, it prints every problem and exits `1`.

## Endpoint

| Method | Path | Middleware | Request | Response | Errors |
|---|---|---|---|---|---|
| GET | `/api/v1/categories` | `auth:sanctum`, `throttle:60,1`, `cache.headers:private;no_cache;etag` | — (reads `If-None-Match`) | `200` `CategoryResource` collection, or `304` with no body | 401, 429 |

`CategoryController::index` calls `ListCategories` and returns the collection. No policy: R1 makes the list readable by every signed-in account, and it holds no account or household data. Full bodies in [contract.md](./contract.md).

`CategoryResource` (one class, used at each level) gives:

- every node: `code`, `translation_key`, `emoji`, `retired` (K5);
- tops: `type` and `groups`;
- groups: `leaves`;
- leaves: `is_other`.

## Error codes

No new code. `auth.unauthenticated` (401) and `request.throttled` (429) come from spec 001 and spec 002.

## Docs

- `CLAUDE.md` (REQ-3.1):
  - "Current state": categories exist (spec 005); transactions come with spec 006.
  - "Seeding" becomes "Categories": three levels (top, group, leaf), stable codes never renamed or reused, the catalogue file, `ledger:sync-categories` after every migrate, retire instead of delete, no type change, translation keys instead of names (C5).
  - "API → Format": lists are cursor-paginated, except the category tree (K7).
  - Module layout: `Commands/` (Artisan commands) and `resources/`.
  - The Ledger row of the module table and the roadmap numbers (transactions 006, summaries 007).
- `README.md` (REQ-2.7, REQ-3.3): status line; setup step 4 runs `php artisan ledger:sync-categories` after `migrate`; a new "Deploying" note (migrate, then sync); a "Changing the category list" section (edit the file, run the command, never rename or reuse a code, never change a type, add the three translations to the app).
- Postman (REQ-3.2): folder `Categories (spec 005)` with `GET categories`, its description covering `ETag`, `304`, `401` and `429`. No new environment variable.

## Requirement coverage

| REQ | Covered by |
|---|---|
| 1.1, 1.2, 1.5 | `ListCategories`, `CategoryResource`; `ListCategoriesTest` (full tree, order, fields, cookie and token give the same body) |
| 1.3 | `auth:sanctum`; `ListCategoriesTest` (both modes) |
| 1.4 | K6; `ListCategoriesTest` (`ETag` present, `304` with no body, new tag after a sync changes an emoji) |
| 1.6 | K4, K5; `ListCategoriesTest` (retired leaf, and a retired group's leaves shown retired) |
| 2.1, 2.8 | K1; `ShippedCatalogueTest` (file valid, codes, emoji, types and order equal to Appendix A parsed from `requirements.md`, syncs on an empty database) |
| 2.2, 2.3, 2.6, 2.9 | `SyncCategories`; `SyncCategoriesTest` (create, update, retire, restore, idempotent, translation key, row count never drops) |
| 2.4, 2.5 | `CategoryCatalogue`, K8; `CategoryCatalogueTest` (one test per rule, several problems listed at once), `SyncCategoriesTest` (type change refused, nothing written), `SyncCategoriesCommandTest` (exit codes and output) |
| 2.7, 3.1–3.3 | **Docs** |
| 2.10 | K11; `ShippedCatalogueTest` (each of `en`, `fr`, `ar` has exactly the catalogue's keys, names equal to Appendix A) |
| 3.4 | The tests above |
| 3.5 | Existing arch tests, unchanged |

## Risks / alternatives considered

- **JSON or YAML catalogue**: easier for a non-developer to read, but needs a parser (YAML) or loses Pint and Larastan's checks, and the owner edits it in the code editor anyway. PHP array chosen (K1).
- **A seeder instead of a command**: CLAUDE.md keeps seeders out of tests and production, and a seeder neither retires nor reports. Rejected.
- **A migration per list change**: makes every wording or emoji change a migration, against US-3. Rejected.
- **A stored list version for the `ETag`** (bumped by the sync): avoids building the body on a `304`, but adds state for a 170-row read. The body hash is enough today; revisit if the read shows up in measurements.
- **Names in the database**: refused by C5. The cost is that a new category shows its parent's name in the app until the app ships its translations (R7).
- **Forgetting to run the sync after a deploy**: the list would be empty or out of date. The README makes it part of setup and deploy; spec 006 can refuse entries cleanly while a category is missing.
