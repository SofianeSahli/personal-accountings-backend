# 005 — Categories: Tasks

> Status: Approved (2026-11-22), with the design, when the owner asked to implement it and open the PR
> Requirements: [requirements.md](./requirements.md) · Design: [design.md](./design.md) · Contract: [contract.md](./contract.md)

Each task: tests first, then code, then `php artisan test`, Pint and Larastan.

- [x] **T1 — Table, model, enums, factory** (REQ-2.6, REQ-2.9)
  - Migration `create_categories_table` (design "Data"), `Category` (no `SoftDeletes`, `children()` ordered by `position`), `CategoryType`, `CategoryLevel`, `CategoryFactory` (states `code`, `under`, `retired`).
  - Verified by: `CategoryModelTest` (type `CHECK`, unique code, parent FK refuses deleting a parent).
- [x] **T2 — Catalogue check** (REQ-2.4)
  - `CatalogueEntry`, `CategoryCatalogue::fromArray()`, `InvalidCatalogue`.
  - Verified by: `CategoryCatalogueTest`, one test per rule of design "Catalogue rules", plus several problems reported at once.
- [x] **T3 — Sync action** (REQ-2.2, 2.3, 2.5, 2.6, 2.9)
  - `SyncCategories`, `SyncReport`.
  - Verified by: `SyncCategoriesTest` (create, update, retire, restore, idempotent, translation key, type change refused with nothing written, never deletes).
- [x] **T4 — Command** (REQ-2.2, 2.4)
  - `SyncCategoriesCommand` (`ledger:sync-categories`, `--file`), registered in `LedgerServiceProvider`.
  - Verified by: `SyncCategoriesCommandTest` (summary line, exit `0`; invalid file lists problems, exit `1`, nothing written; missing file).
- [x] **T5 — Shipped catalogue** (REQ-2.1, 2.8, 2.10)
  - `app/Modules/Ledger/resources/categories.php` from Appendix A.
  - Verified by: `ShippedCatalogueTest` (valid, equal to Appendix A, syncs; `en`/`fr`/`ar` translations hold exactly its keys with Appendix A's names).
- [x] **T6 — Endpoint** (REQ-1.1–1.6)
  - `ListCategories`, `CategoryResource`, `CategoryController`, route with `auth:sanctum`, `throttle:60,1`, `cache.headers`.
  - Verified by: `ListCategoriesTest` (tree, order, fields, retired inheritance, `401`, `ETag`, `304`, both modes give the same body).
- [x] **T7 — Docs and Postman** (REQ-2.7, REQ-3.1–3.3)
  - `CLAUDE.md`, `README.md`, Postman folder `Categories (spec 005)`.
  - Verified by: reading; the collection is valid JSON.

Deviations:

- T1: the type `CHECK` is a second migration (one schema change per migration, as for `books`).
- T6: spec 001's `HealthTest` (REQ-12.3) listed `GET /api/v1/categories` as a removed legacy path. The route exists again (spec 001, REQ-12.5), so that one dataset row is removed and REQ-12.3's example updated; the other legacy paths are still tested. Pending the owner's approval in the PR.
- Tests ran in CI (MySQL 8.4): Docker Desktop could not start on the development machine.
