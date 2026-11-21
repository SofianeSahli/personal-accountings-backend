# 003 — Books and households: Tasks

> Status: Done (2026-11-20). Approved by the owner on 2026-11-20 together with the design ("go ahead with the plan then task and implement"); deviations below await review in the PR.
> Design: [design.md](./design.md) · Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

Order: migrations/models → actions → policies → controllers/requests/resources → routes → docs.
Each task: its tests first (named `REQ-x.y …`), then the code; `php artisan test`, Pint and Larastan pass.

- [x] **T1** — Shared wiring: denial codes and the creations limiter
  - Fulfils: REQ-2.8, REQ-8.3, REQ-8.4 (K7, K12)
  - Files: `ApiErrorRenderer` (policy denial code arm), `Shared/Http/CreationLimit`, `AppServiceProvider`, spec 001 `contract.md`
  - Verify: `ErrorRenderingTest` (a denial with a string code keeps it; without, `auth.forbidden` / `request.not_found` as before)

- [x] **T2** — Identity: account names
  - Fulfils: REQ-7.3, REQ-9.2
  - Files: `Identity/Actions/Accounts/FindAccountNames`
  - Verify: `FindAccountNamesTest`

- [x] **T3** — Household tables and models
  - Fulfils: REQ-2.2 (data side), REQ-8.6, REQ-8.9, REQ-2.12 (K1, K2, K15)
  - Files: 2 migrations, `Household`, `HouseholdMember`, `HouseholdRole`, factories, `HouseholdServiceProvider` (migrations, morph alias), `bootstrap/providers.php`, `phpstan.neon`
  - Verify: `migrate:fresh`; `HouseholdDataTest` (second live membership refused by the unique index; deleted membership does not block)

- [x] **T4** — Ledger table and model
  - Fulfils: REQ-8.8, REQ-2.3 (data side) (K3, K4, K15)
  - Files: migration, `Book`, `BookFactory`, `LedgerServiceProvider` (migrations, morph alias `account`, `enforceMorphMap`), `bootstrap/providers.php`, `phpstan.neon`
  - Verify: `BookIntegrityTest` (insert without owner and with an unknown owner type refused; second live household book refused)

- [x] **T5** — Household actions, policy, middleware, endpoints
  - Fulfils: REQ-0.1, 0.4, 2.1, 2.2, 2.4–2.10, 2.12, 7.1–7.3, 8.1–8.6, 9.5, 9.6
  - Files: `CreateHousehold`, `RenameHousehold`, `DeleteHousehold`, `ListHouseholds`, `ListMembers`, `FindMembership`, `Support/{Membership,CurrentHousehold,HouseholdPage,MemberPage}`, `HouseholdDeleted`, `HouseholdPolicy`, `RequireHousehold`, controllers, requests (`HouseholdRules`), resources (`HouseholdList`, `MemberList`), `routes.php`, `Tests/Support/HouseholdScenario`
  - Verify: `CreateHouseholdTest`, `ListHouseholdsTest`, `HouseholdAccessTest` (show, 404s, 401 first), `UpdateHouseholdTest`, `DeleteHouseholdTest`, `MembersTest`

- [x] **T6** — Ledger actions, policy, middleware, endpoints, listener
  - Fulfils: REQ-0.1–0.4, 1.1–1.3, 2.3, 2.8, 2.11, 3.1, 3.2, 4.1, 4.2, 5.1–5.4, 8.1–8.5, 8.7, 8.9, 9.3, 9.5, 9.6
  - Files: `CreateBook`, `UpdateBook`, `ListBooks`, `DeleteHouseholdBook`, `Support/{NewBook,BookChanges,BookPage,CurrentBook}`, `Rules/{SupportedCurrency,IanaTimezone}`, `SoftDeleteHouseholdBook`, `BookPolicy`, `RequireBook`, controller, requests (`BookRules`), resources (`BookResource`, `BookList`), `routes.php`
  - Verify: `CreateBookTest` (incl. the shared creations limit), `ListBooksTest`, `BookAccessTest` (show, 404s, 401 first), `UpdateBookTest`, `HouseholdBookDeletionTest`

- [x] **T7** — Cross-cutting checks
  - Fulfils: REQ-1.4, REQ-9.1, REQ-9.4
  - Files: `NoAutomaticCreationTest` (Household and Ledger), `NoPersonalDataInLogsTest` (Ledger), `IdentityScenario::signUpAndSignInEveryWay`, arch tests unchanged
  - Verify: those tests; `route:list --path=api` matches the contract

- [x] **T8** — Docs and Postman
  - Fulfils: REQ-9.7, REQ-9.8
  - Files: `CLAUDE.md`, `README.md`, `postman/*`
  - Verify: review; collection is valid JSON

## Deviations from spec
(Record any change made during implementation and when it was approved.)

Made during implementation on 2026-11-20, to be approved in the PR review:

- **D1** — Lists keep Eloquent models, rendered by `HouseholdList`, `MemberList` and `BookList` resource collections, each built from a page DTO: `HouseholdPage` (page + caller's `Membership`), `MemberPage` (page + names) and `BookPage`. Laravel computes `next_cursor` from the items on the page, so mapping them to a view DTO first breaks the cursor. `MemberView` is dropped.
- **D2** — The `CHECK (owner_type IN ('account', 'household'))` on `books` is its own migration (`2026_11_20_000004_add_owner_type_check_to_books_table.php`). The schema builder has no check constraint, and CLAUDE.md wants one schema change per migration.
- **D3** — Laravel turns an `AuthorizationException` into an `HttpException` before render callbacks run, so `ApiErrorRenderer` now looks at the wrapped `AuthorizationException`. It reads the denial code with `toResponse()->code()`. Before this, a `denyAsNotFound()` without a code gave `500 server.error` instead of `404 request.not_found`; the new probe test covers both.
- **D4** — `Book::isReachableBy(User, ?Membership)` sits next to `canBeManagedBy`, both pure, used by `BookPolicy`.
- **D5** — Request rule helpers `HouseholdRules` and `BookRules`, like `IdentityRules`. On create, `currency` and `timezone` are `nullable` (`null` means the default) and use `bail`, so a non-string gives only `.string`, as the contract lists.
- **D6** — `RequireHousehold` and `RequireBook` check the id with `Str::isUuid` before querying, so a malformed id never reaches MySQL. It still goes through the policy denial, giving the same `404`.
- **D7** — `phpunit.xml` sets `memory_limit` to 512M. The full suite (426 tests) went past the container's 128M default; each module alone passes under it.
- **D8** — Test support: `WebBrowser::signedInAs()` and `patchJson()`, `IdentityScenario::signUpAndSignInEveryWay()`, `HouseholdScenario` (`ownedBy`, `join`, `memberOf`, `leave`, `as`). `HouseholdScenario::as()` resets the auth guards, like `IdentityScenario`, so one test can act as several users.
- **D9** — "Book unreachable when the listener fails" (design K16) is tested with `Event::fake([HouseholdDeleted::class])`, which is the same state: the household is deleted and the book is not. The listener's `catch` (log ids only, keep the `204`) has no test of its own, because the action is a final class that cannot be made to fail without a test seam.
- **D10** — `GET`/`POST households` and `GET`/`POST books` call no policy. They touch only the caller's own records, and the household checks on `POST books` (`household_id.not_found`, `household.forbidden`) live in `CreateBook`, as the design's endpoint table says. Every route with an id in the path goes through a policy.
- **D11** — `enforceMorphMap` maps `User` to `account`, so Sanctum now stores `tokenable_type = 'account'` in `personal_access_tokens`. There is no data to migrate.

## Verification report
| REQ | Verified how | Result |
|---|---|---|
| 0.1–0.4 | `CreateHouseholdTest`, `UpdateHouseholdTest`, `CreateBookTest`, `UpdateBookTest` (one code per field, nothing changed) | Pass |
| 1.1–1.3 | `CreateBookTest` (both modes, defaults, limit of 5, household and deleted books not counted) | Pass |
| 1.4 | `NoAutomaticCreationTest` in Household and Ledger (email sign-up, password sign-in, Google sign-in) | Pass |
| 2.1–2.2 | `CreateHouseholdTest` (both modes, owner or member already, race through the unique index), `HouseholdDataTest` | Pass |
| 2.3 | `CreateBookTest` (owner, 3 × `household_id.not_found`, member 403, second book, race), `BookIntegrityTest` | Pass |
| 2.4–2.7 | `ListHouseholdsTest`, `HouseholdAccessTest`, `UpdateHouseholdTest` (member 403 before validation) | Pass |
| 2.8 | `CreateHouseholdTest` (10 then 429, other account unaffected), `CreateBookTest` (households and books counted together) | Pass |
| 2.9–2.12 | `DeleteHouseholdTest`, `HouseholdBookDeletionTest`, `HouseholdDataTest` (rows kept with `deleted_at`) | Pass |
| 3.1–3.2 | `ListBooksTest` (order, only mine, `can_manage`, cursor) | Pass |
| 4.1–4.2 | `BookAccessTest`, `ListBooksTest` (membership gone, book gone) | Pass |
| 5.1–5.4 | `UpdateBookTest` | Pass |
| 7.1–7.3 | `MembersTest` (order, fields, current names, cursor), `FindAccountNamesTest` | Pass |
| 8.1–8.5 | 401 tests per endpoint; `HouseholdAccessTest` and `BookAccessTest` (unknown, malformed, foreign, deleted: same status, body and headers); `ErrorRenderingTest` (named denial codes) | Pass |
| 8.6–8.9 | `HouseholdDataTest`, `BookIntegrityTest` (no owner, unknown owner type, stored type `account`), soft-delete tests above | Pass |
| 9.1–9.3 | `tests/Architecture` (module dependencies and other modules' models, Ledger tests included) | Pass |
| 9.4 | `NoPersonalDataInLogsTest` (Ledger) | Pass |
| 9.5–9.6 | Both modes per endpoint (`WebBrowser`), test names start with their REQ | Pass |
| 9.7–9.8 | Postman folder `Books and households (spec 003)` with 11 requests (two for `POST books`), environment gains 6 variables; CLAUDE.md and README updated | Review |
| Routes | `route:list --path=api/v1`: the 10 routes of `contract.md` | Pass |
| Gate | `php artisan test` 426 passed; Pint clean; Larastan level 6 no errors; `composer audit` clean | Pass |
