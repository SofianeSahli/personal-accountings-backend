# 006 — Transactions and recurring rules: Design

> Status: Approved (2026-11-22), when the owner asked to do all the remaining work and open the PR for review
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Everything lives in `Ledger`. Two new tables: `transactions` (the entries) and `recurring_rules`. Entries and rules are nested under a book, behind the existing `RequireBook` middleware, which already gives access to the personal book's account and to every live member of a household's book (REQ-0.1, 0.2). A pure value object, `Schedule`, computes a rule's due dates. An hourly Artisan command calls the action that records every due entry. A new `scheduler` service in `docker-compose.yml` runs Laravel's scheduler.

`Household` and `Identity` do not change: `Ledger` calls `FindMembership` (through `RequireBook`) and `FindAccountNames`.

## Key decisions

| # | Decision | Why |
|---|---|---|
| K1 | The entry table is `transactions` and its model `Transaction` (CLAUDE.md names), but the API and the app say "entry" nowhere in paths: paths are `/transactions` | CLAUDE.md, roadmap |
| K2 | An entry stores its **leaf** `category_id`. Its type is read from the category (`categories.type`, copied on every row by spec 005 K3), not stored again. Filtering by type or by a top or group uses `category_id IN (sub-select on categories)` | E3, REQ-2.2 |
| K3 | Checks that need the category (unknown, not a leaf, retired, the "Other" rule) run in the action `ChooseCategory`, which throws `ApiException('validation.failed', 422, fields: …)`, the same pattern as `CreateBook`'s `household_id.not_found`. The Form Request checks only shape (types, lengths, dates). So a request with both shape and category errors gets the shape errors first | REQ-0.4, R3, R4 |
| K4 | A generated entry stores the due date it was made for in `rule_due_on`, never changed, with a **unique** index `(recurring_rule_id, rule_due_on)`. Soft-deleted entries keep their row, so a deleted occurrence is never made again; the index stops two jobs from making it twice. The entry's own `date` starts equal to `rule_due_on` and may be edited | R7, REQ-4.6 |
| K5 | A rule stores `next_due_on` (the next date to make, `null` when finished). The job only looks at rules with `next_due_on` ≤ today + 1 day in UTC (no timezone is more than 14 hours ahead), then compares with each book's own today | REQ-4.5, R10 |
| K6 | One action, `CatchUpRecurringRule`, makes a rule's missing entries up to its book's today, under a `lockForUpdate` on the rule row. Create (backfill, E12), edit (R8) and the job all call it, so the rule is written once | REQ-4.4, 4.5, 4.9 |
| K7 | Editing a rule first catches it up with the **old** values, then applies the changes. So "entries not yet created" means exactly the future ones (E13). If the schedule changed, `next_due_on` becomes the new schedule's first due date on or after today, and a second catch-up makes today's entry if it is due (R8) | REQ-4.9 |
| K8 | Due dates are always computed from `starts_on` (`starts_on + k × interval` with "no overflow" month and year arithmetic), never from the previous due date. So 31 January → 28/29 February → 31 March, and 29 February → 28 February → 29 February in the next leap year | E10, REQ-4.3 |
| K9 | The client id (E9) is the primary key. `RecordTransaction` looks it up with trashed rows first; on a race the primary key's `UniqueConstraintViolationException` leads to the same lookup. Same book, live → `200` with the stored entry; else `409 transaction.id_conflict` | REQ-1.3, R12 |
| K10 | A rule's list order is "next date, rules with none last". Cursor pagination needs plain columns, so the table has a stored generated column `due_order` = `COALESCE(next_due_on, '9999-12-31')` | REQ-4.8 |
| K11 | Writes share one rate limiter, `ledger-writes`, 60 a minute per account, defined by `LedgerWriteLimit` in `Ledger/Support` and set on each write route | REQ-0.6, R13 |
| K12 | The rule limit (100 per book) counts live rules under a `lockForUpdate` on the book row, in the create transaction, so two parallel creates cannot pass it | R14, REQ-4.11 |
| K13 | Names (`created_by`, `updated_by`) come from one `FindAccountNames` call per answer (one per page in lists). An account that left the household is still found: users are never deleted | REQ-2.5, R6 |
| K14 | Suggestions (R11) read the latest 500 uses of that leaf in the book (entries and rules, live only), group them in PHP by `mb_strtolower`, sort by count then last use, keep 10. Grouping in SQL would use the column's accent-insensitive collation, which merges "café" and "cafe" | REQ-5.1, 5.2 |
| K15 | An entry or rule id in the path is a plain string. The controller loads it with `Transaction::inBook()` / `RecurringRule::inBook()` (malformed id → `null`) and authorizes with `TransactionPolicy` / `RecurringRulePolicy`, which deny as not found when it is `null` or belongs to another book. No implicit route binding, so every miss gives the module's own code | REQ-0.3 |
| K16 | "Today" in a book is `CarbonImmutable::now($book->timezone)->toDateString()`. Dates are handled as `Y-m-d` strings and `CarbonImmutable` at midnight UTC for arithmetic, so no timezone leaks into date maths | E4, REQ-6.5 |
| K17 | The job is the command `ledger:record-due-entries`, scheduled `hourly()->withoutOverlapping()` from `LedgerServiceProvider`. One failing rule is logged (`ledger.recurring_rule_failed`, rule and book ids) and skipped; the others go on | REQ-4.5, 4.7, 6.6 |

## Placement

| Piece | Path |
|---|---|
| `Transaction`, `RecurringRule` models; `Frequency` enum (`Weekly`, `Monthly`, `Yearly`) | `app/Modules/Ledger/Models/` |
| `NewTransaction`, `TransactionChanges`, `NewRecurringRule`, `RecurringRuleChanges`, `CategoryChoice`, `ChosenCategory`, `Schedule`, `BookDay`, `TransactionFilters`, `TransactionPage`, `RecurringRulePage`, `LedgerWriteLimit` | `app/Modules/Ledger/Support/` |
| Actions (below) | `app/Modules/Ledger/Actions/` |
| `RecordDueEntriesCommand` | `app/Modules/Ledger/Commands/` |
| `TransactionController`, `RecurringRuleController`, `OtherNameController` | `app/Modules/Ledger/Controllers/` |
| Form Requests, Resources | `app/Modules/Ledger/Controllers/Requests/`, `…/Resources/` |
| `TransactionPolicy`, `RecurringRulePolicy` | `app/Modules/Ledger/Policies/` |
| Migrations, `TransactionFactory`, `RecurringRuleFactory` | `app/Modules/Ledger/database/…` |
| Tests | `app/Modules/Ledger/Tests/Transactions/`, `…/Tests/RecurringRules/`, `…/Tests/Support/LedgerScenario.php` |

Cross-module calls: `FindMembership` (already, in `RequireBook`) and `Identity`'s `FindAccountNames`. Foreign keys to `users.id` are ids only. Architecture tests unchanged (REQ-6.7).

## Data

One schema change per migration. Rules come first, because entries point to them.

### `recurring_rules`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK, ascii | `HasUuids` |
| `book_id` | uuid ascii | FK `books.id`, restrict |
| `category_id` | uuid ascii | FK `categories.id`, restrict; a leaf |
| `amount` | bigint unsigned | minor units, 1 … 999 999 999 999 |
| `other_name` | varchar(60) null | only on an "Other" leaf |
| `comment` | varchar(200) null | |
| `frequency` | varchar(10) ascii | `CHECK (frequency IN ('weekly', 'monthly', 'yearly'))` (own migration) |
| `interval` | tinyint unsigned | 1–12 |
| `starts_on` | date | |
| `ends_on` | date null | |
| `next_due_on` | date null | K5 |
| `due_order` | date, stored generated | `COALESCE(next_due_on, '9999-12-31')` (K10) |
| `created_by`, `updated_by` | uuid ascii | FK `users.id`, restrict |
| `created_at`, `updated_at`, `deleted_at` | timestamps | soft deletes |

Indexes: `(book_id, deleted_at, due_order, id)` for the list; `(next_due_on)` for the job.

### `transactions`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK, ascii | `HasUuids`, or the client's id (K9) |
| `book_id` | uuid ascii | FK `books.id`, restrict |
| `category_id` | uuid ascii | FK `categories.id`, restrict; a leaf |
| `recurring_rule_id` | uuid ascii null | FK `recurring_rules.id`, restrict |
| `rule_due_on` | date null | K4 |
| `amount` | bigint unsigned | minor units |
| `date` | date | |
| `other_name` | varchar(60) null | |
| `comment` | varchar(200) null | |
| `created_by`, `updated_by` | uuid ascii | FK `users.id`, restrict |
| `created_at`, `updated_at`, `deleted_at` | timestamps | soft deletes |

Indexes: unique `(recurring_rule_id, rule_due_on)` (K4); `(book_id, deleted_at, date, created_at, id)` for the list; `(book_id, category_id)` for the category filter and suggestions; `(book_id, created_by)` for the author filter.

No `ON DELETE CASCADE` anywhere (REQ-6.3).

## Support objects

- `BookDay::today(Book)`: the book's today as `Y-m-d` (K16); `BookDay::shift(string $date, string $modifier)`.
- `Schedule` (readonly): `frequency`, `interval`, `startsOn`, `?endsOn`. `dueDate(int $k): ?string` (`null` past `endsOn`), `firstOnOrAfter(string $date): ?string`. Pure; unit-tested (REQ-6.5).
- `CategoryChoice` (readonly): `?Category $current`, `?string $currentOtherName`, `?string $code` (sent), `bool $sendsOtherName`, `?string $otherName`. `ChosenCategory` (readonly): `Category $category`, `?string $otherName`.
- `NewTransaction`, `TransactionChanges`, `NewRecurringRule`, `RecurringRuleChanges`: readonly DTOs built by the Form Requests. "Changes" DTOs carry one `bool` per optional field to tell "not sent" from "sent as null" (`comment`, `other_name`, `ends_on`).
- `TransactionFilters` (readonly): `?from`, `?to`, `?categoryCode`, `?CategoryType $type`, `?authorId`.
- `TransactionPage` / `RecurringRulePage`: the cursor page plus the `id → name` map (K13).

## Actions

| Action | Input → output | Rules | Transaction / lock |
|---|---|---|---|
| `ChooseCategory` | `CategoryChoice` → `ChosenCategory` | REQ-0.4, R3, R4. Code sent: unknown → `category.not_found`; not a leaf → `category.not_leaf`; retired and different from the current one → `category.retired`. Then the result's "Other" rule: an "Other" leaf needs a name (sent, or kept when the leaf did not change) → `other_name.required`; any other leaf with a name sent → `other_name.prohibited`, and a name not sent is cleared | — |
| `RecordTransaction` | `Book`, `User`, `NewTransaction` → `RecordedTransaction` (`Transaction`, `bool $created`) | REQ-1.1–1.4, K9. `ChooseCategory`, insert with the caller as `created_by` and `updated_by` | insert |
| `ListTransactions` | `Book`, `TransactionFilters` → `TransactionPage` | REQ-2.1, 2.2, 2.5, R15. Unknown filter category → `category.not_found`. `with('category')`, order `date desc, created_at desc, id desc`, 25 per page | — |
| `UpdateTransaction` | `Transaction`, `User`, `TransactionChanges` → `Transaction` | REQ-3.1–3.3, R16. Never touches `book_id`, `created_by`, `recurring_rule_id`, `rule_due_on` | — |
| `DeleteTransaction` | `Transaction` → void | REQ-3.4. Soft delete | — |
| `CreateRecurringRule` | `Book`, `User`, `NewRecurringRule` → `RecurringRule` | REQ-4.1, 4.2, 4.4, 4.11, R9. `ChooseCategory`; limit under the book lock (K12); `next_due_on = starts_on`; then `CatchUpRecurringRule` (backfill) | `DB::transaction`, `lockForUpdate` on the book |
| `CatchUpRecurringRule` | `RecurringRule` → `int` (entries made) | REQ-4.4–4.6, K4–K6. Reloads the rule with `lockForUpdate`; stops if the rule or its book is deleted (REQ-4.12); while `next_due_on` ≤ book today: makes the entry unless one exists for `(rule, due date)` with trashed rows, then moves `next_due_on` to the next due date (`null` past `ends_on`) | `DB::transaction` |
| `UpdateRecurringRule` | `RecurringRule`, `User`, `RecurringRuleChanges` → `RecurringRule` | REQ-4.9, K7, R8. Catch up; `ChooseCategory`; `ends_on` before the resulting `starts_on` → `ends_on.after_or_equal`; schedule changed → `next_due_on` = `Schedule::firstOnOrAfter(today)`; save; catch up again | `DB::transaction` |
| `DeleteRecurringRule` | `RecurringRule` → void | REQ-4.10. Soft delete; entries untouched | — |
| `ListRecurringRules` | `Book` → `RecurringRulePage` | REQ-4.8, K10 | — |
| `RecordDueEntries` | — → `DueEntriesReport` (`rules`, `entries`, `failures`) | REQ-4.5, 4.7, K5, K17. Rule ids with `next_due_on` ≤ UTC today + 1 day, live rule, live book; `CatchUpRecurringRule` on each; a `Throwable` is logged with ids only and counted | per rule |
| `SuggestOtherNames` | `Book`, `string $code` → `list<string>` | REQ-5.1–5.3, K14. Unknown → `category.not_found`; not an "Other" leaf → `category.not_other` | — |

## Endpoints

All under `/api/v1/books/{book}`, behind `auth:sanctum` and `RequireBook`. Write routes also carry `throttle:ledger-writes`.

| Method | Path | Controller | Request | Response | Errors |
|---|---|---|---|---|---|
| GET | `transactions` | `TransactionController@index` | `ListTransactionsRequest` | `200` `TransactionList` | 401, 404, 422 |
| POST | `transactions` | `@store` | `RecordTransactionRequest` | `201` (or `200` on a repeated id) `TransactionResource` | 401, 404, 409, 422, 429 |
| GET | `transactions/{transaction}` | `@show` | — | `200` | 401, 404 |
| PATCH | `transactions/{transaction}` | `@update` | `UpdateTransactionRequest` | `200` | 401, 404, 422, 429 |
| DELETE | `transactions/{transaction}` | `@destroy` | — | `204` | 401, 404, 429 |
| GET | `recurring-rules` | `RecurringRuleController@index` | — | `200` `RecurringRuleList` | 401, 404 |
| POST | `recurring-rules` | `@store` | `CreateRecurringRuleRequest` | `201` `RecurringRuleResource` | 401, 404, 422, 429 |
| GET | `recurring-rules/{rule}` | `@show` | — | `200` | 401, 404 |
| PATCH | `recurring-rules/{rule}` | `@update` | `UpdateRecurringRuleRequest` | `200` | 401, 404, 422, 429 |
| DELETE | `recurring-rules/{rule}` | `@destroy` | — | `204` | 401, 404, 429 |
| GET | `other-names` | `OtherNameController@index` | `SuggestOtherNamesRequest` | `200` `{"data": ["…"]}` | 401, 404, 422 |

Policies (K15): `TransactionPolicy::access(User, ?Transaction, Book)` and `RecurringRulePolicy::access(User, ?RecurringRule, Book)`, `Response::denyAsNotFound(code: 'transaction.not_found' | 'recurring_rule.not_found')`. Book access is already checked by `RequireBook` (`BookPolicy::view`), so any live member passes (E2).

Form Request rules (shape only, K3):

| Field | Rules |
|---|---|
| `id` | `nullable`, `uuid` |
| `amount` | `required` (create) / `sometimes, required` (update), `integer:strict`, `min:1`, `max:999999999999` |
| `date` | `required` / `sometimes, required`, `date_format:Y-m-d`, `after_or_equal:2000-01-01`, `before_or_equal:<book today + 1 year>` |
| `category` | `required` / `sometimes, required`, `string` |
| `other_name` | `nullable`, `string`, `max:60` |
| `comment` | `nullable`, `string`, `max:200` |
| `frequency` | `required` / `sometimes, required`, `in:weekly,monthly,yearly` |
| `interval` | `nullable` / `sometimes, required`, `integer:strict`, `min:1`, `max:12` |
| `starts_on` | `required` / `sometimes, required`, `date_format:Y-m-d`, `after_or_equal:<today − 1 year>`, `before_or_equal:<today + 1 year>` |
| `ends_on` | `nullable`, `date_format:Y-m-d`, `after_or_equal:starts_on` (create; on update the action compares with the stored `starts_on`) |
| list `from`, `to` | `nullable`, `date_format:Y-m-d`; `to` also `after_or_equal:from` |
| list `category` | `nullable`, `string` |
| list `type` | `nullable`, `in:expense,income,saving` |
| list `author` | `nullable`, `uuid` |

Trimming and empty-to-`null` come from Laravel's global `TrimStrings` and `ConvertEmptyStringsToNull` (E5, E6).

## Error codes

New: `transaction.not_found` (404), `transaction.id_conflict` (409), `recurring_rule.not_found` (404), `recurring_rule.limit_reached` (422), and the field codes `category.not_found`, `category.not_leaf`, `category.retired`, `category.not_other`, `other_name.required`, `other_name.prohibited`. Existing: `auth.unauthenticated`, `book.not_found`, `validation.failed`, `request.throttled`.

## Scheduler

- `LedgerServiceProvider` registers `RecordDueEntriesCommand` and, through `callAfterResolving(Schedule::class, …)`, schedules it `hourly()->withoutOverlapping()` (the lock uses the `database` cache).
- `docker-compose.yml` gets a `scheduler` service (same image as `queue`, `php artisan schedule:work`) (REQ-7.1).
- README "Deploying": run `php artisan schedule:run` every minute from cron (or `schedule:work` under a supervisor).

## Docs

- `CLAUDE.md` (REQ-7.3): "Current state" (endpoints of spec 006 and the scheduler; spec 007 next), Docker bullet (the `scheduler` service), module table (Ledger: books, categories, transactions, recurring_rules; then the summaries), commands list (`ledger:record-due-entries`).
- `README.md` (REQ-7.4): status line, endpoints table, the scheduler in setup and deploy.
- Postman (REQ-7.2): folder `Transactions (spec 006)` with one request per route; variables `transaction_id`, `recurring_rule_id`, `category_code`, `other_category_code`, stored by test scripts where returned.
- `contract.md`: every route above.

## Requirement coverage

| REQ | Covered by |
|---|---|
| 0.1, 0.2, 0.3, 0.7 | `RequireBook`, K15; `TransactionAccessTest`, `ManageRecurringRulesTest` (401 both modes, other household, other personal book, deleted book, another book's entry/rule, malformed id, member writes) |
| 0.4, 1.2 | Form Requests, `ChooseCategory`; `RecordTransactionTest` (datasets per field code) |
| 0.5, 6.2 | resources, `bigint`; exact integer asserts |
| 0.6 | K11; `TransactionAccessTest` |
| 1.1, 1.3, 1.4 | `RecordTransaction`; `RecordTransactionTest` |
| 2.1–2.5 | `ListTransactions`; `ListTransactionsTest` |
| 3.1–3.6 | `UpdateTransaction`, `DeleteTransaction`; `UpdateTransactionTest` |
| 4.1, 4.2, 4.4, 4.8–4.11 | rule actions; `CreateRecurringRuleTest`, `ManageRecurringRulesTest` |
| 4.3, 6.5 | `Schedule`, `BookDay`; `ScheduleTest` (unit) |
| 4.5–4.7, 4.12 | `CatchUpRecurringRule`, `RecordDueEntries`; `RecordDueEntriesTest` |
| 5.1–5.3 | `SuggestOtherNames`; `SuggestOtherNamesTest` |
| 6.1, 6.3 | migrations; `TransactionIntegrityTest` (FKs restrict, unique occurrence) |
| 6.4 | every test above |
| 6.6 | `NoMoneyDataInLogsTest`, `RecordDueEntriesTest` |
| 6.7 | existing arch tests |
| 7.1–7.4 | **Docs**, `docker-compose.yml`, Postman |

## Risks / alternatives considered

- **Materialising ahead of time** (entries for the next N days): refused by E11; entries never exist before their date.
- **Computing due dates from the previous one**: drifts after a short month (31 → 28 → 28 …). K8 anchors on `starts_on`.
- **A `type` column on entries**: faster type filters and summaries, but a second truth beside the category. Spec 007 can add it if summaries need it; spec 005 R5 forbids type changes, so it could be backfilled safely.
- **Category checks as custom validation rules**: every code would be `<field>.<rule class>`, and the "Other" rule needs the stored entry. Done in an action instead (K3).
- **The scheduler not running in production**: rules would stop. The README makes it a deploy step; the job catches up all missed days when it runs again (E11).
