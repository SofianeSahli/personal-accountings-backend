# 008 — Budget: Design

> Status: Approved (2026-11-25) — implemented
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Everything lives in `Ledger`. One resource, the budget of a month, under the existing `RequireBook` middleware: `GET`, `PUT` and `POST …/copy` on `/api/v1/books/{book}/budgets/{month}` (R1). Two new tables hold the plan. The comparison with actual is computed on each read from `transactions`, with the by-leaf query that spec 007 already uses, extracted into its own action so both specs share it (REQ-6.1, 6.3). Nothing about actual is stored.

`Household` and `Identity` do not change, and no `Identity` action is called: the budget shows no account names.

## Key decisions

| # | Decision | Why |
|---|---|---|
| K1 | Two tables: `budget_plans` (one row per book and month: expected income, planned saving) and `budget_plan_lines` (one row per planned category). A month's plan row is created on the first save and never removed; its lines are replaced by each `PUT` (R15) | REQ-2.1, 2.2, 2.10 |
| K2 | A read runs a fixed set of queries: the plan row, its lines, the by-leaf totals (extracted from spec 007) and the category list. The plan's lines are matched to leaf totals in PHP by climbing `parent_id`, as spec 007's tree does. `book` comes from `RequireBook` | REQ-3.3, 6.1 |
| K3 | The by-leaf query moves from `SummarizePeriod` into a new action `SumByLeaf` (`handle(Book, SummaryPeriod): list<LeafTotal>`, one query joined to `categories` for the type). `SummarizePeriod` calls it; its output and tests do not change. The budget action calls it with the month's period (`SummaryPeriod::month`) | REQ-3.3, 6.1, 6.3 |
| K4 | A line's `actual` is the sum of the leaf totals whose ancestor chain contains the line's category (a group's leaves, or all of a top's groups' leaves). Because a month's lines never overlap (REQ-2.6), each leaf is counted at most once, so `off_plan` is `expense total − Σ line actuals` | REQ-3.3, 4.1, R7, R8 |
| K5 | Status and percent are computed with integer arithmetic only: `actual × 100 ≥ planned × 85` for `warning`, `actual > planned` for `over`, `intdiv(actual × 100, planned)` for the percent. With the largest amount allowed (999 999 999 999) and a month's total below 9 × 10¹⁵, `× 100` stays under PHP's integer limit | REQ-3.4, 6.2, R6 |
| K6 | `SUM` comes back from MySQL as a decimal string and is cast to `int` once, in `SumByLeaf` (spec 007 K5) | REQ-6.2 |
| K7 | The month in the path is validated by `BudgetMonthRequest` rules (`date_format:Y-m`, `after_or_equal:2000-01`, `before_or_equal:<current month + 12>`), the last bound computed from `BookDay::today($book)`. All three routes' form requests share it through one small base trait `ValidatesBudgetMonth`, not a base class | REQ-1.1, 1.2 |
| K8 | The save is an action `SaveBudgetPlan` taking a readonly `NewBudgetPlan` (month, expected income, planned saving, list of `PlanLineInput`). It checks the category rules (REQ-2.3, 2.4, 2.6, 2.7) against the `categories` table read once, then writes in one `DB::transaction`: `updateOrCreate` of the plan row, delete of the old lines, insert of the new ones. The copy action calls `SaveBudgetPlan`, so the rules are written once | REQ-2.1–2.9, 5.1 |
| K9 | **The backend** gives, per line, `planned`, `actual`, `percent` and `status`, in category-tree order. **The app** builds the "À surveiller" list: it keeps lines whose status is `warning` or `over`, sorts them by `percent` (largest first; a `null` percent with status `over` counts as the largest), and keeps the first 3. The backend sends no list for it, so the cap of 3 stays a display choice | B5 |
| K10 | Last write wins (B18): no `version`, no `If-Match`. The whole save is one transaction and the lines are replaced inside it, so two concurrent saves leave one of the two plans, never a mix. The `(book_id, month)` unique index makes two first saves of a month safe (the second `updateOrCreate` retries once as an update) | REQ-2.10 |
| K11 | `days_left` is `monthEnd − today + 1` in the book's timezone for the current month, else `null` (R12), using `BookDay` | REQ-4.3 |
| K12 | Reading the budget is "using the book" (R16): no policy beyond `RequireBook`, like spec 006's lists and spec 007. Writes share `LedgerWriteLimit` (`ledger-writes`) | REQ-0.3, 2.11, 5.6 |

## Placement

| Piece | Path |
|---|---|
| `BudgetPlan`, `BudgetPlanLine` | `app/Modules/Ledger/Models/` |
| `BudgetPlanFactory`, `BudgetPlanLineFactory` | `app/Modules/Ledger/database/factories/` |
| Migrations (one per table) | `app/Modules/Ledger/database/migrations/` |
| `SumByLeaf` (extracted), `ShowBudget`, `SaveBudgetPlan`, `CopyBudgetPlan` | `app/Modules/Ledger/Actions/` |
| `LeafTotal`, `NewBudgetPlan`, `PlanLineInput`, `Budget`, `BudgetLine`, `BudgetMonth` | `app/Modules/Ledger/Support/` |
| `BudgetStatus` (enum, like `CategoryType`) | `app/Modules/Ledger/Models/` |
| `ValidatesBudgetMonth`, `SaveBudgetRequest`, `ShowBudgetRequest`, `CopyBudgetRequest` | `app/Modules/Ledger/Controllers/Requests/` |
| `BudgetResource` | `app/Modules/Ledger/Controllers/Resources/` |
| `BudgetController` (`show`, `update`, `copy`) | `app/Modules/Ledger/Controllers/` |
| Routes | `app/Modules/Ledger/routes.php` |
| Tests | `app/Modules/Ledger/Tests/Budget/` (helpers in `Tests/Support`) |

Cross-module calls: none. The dependency direction `Ledger → Household → Identity` is untouched.

## Data

Keys follow `CLAUDE.md`: `uuid` columns with `ascii` / `ascii_bin`, the same on every foreign key. Amounts are `unsignedBigInteger`.

| Table | Columns | Indexes / FKs | Notes |
|---|---|---|---|
| `budget_plans` | `id` uuid pk; `book_id` uuid; `month` date (first day of the month); `expected_income` unsigned bigint null; `planned_saving` unsigned bigint null; `created_by` uuid; `updated_by` uuid; `created_at`, `updated_at` | unique `(book_id, month)`; FK `book_id` → `books` restrict; FK `created_by`, `updated_by` → `users` restrict | Never soft-deleted or removed (R15); `has_plan` is derived (R3) |
| `budget_plan_lines` | `id` uuid pk; `budget_plan_id` uuid; `category_id` uuid; `planned` unsigned bigint | unique `(budget_plan_id, category_id)`; FK `budget_plan_id` → `budget_plans` restrict; FK `category_id` → `categories` restrict | Replaced by each save; not a money record. `book_id` is reached through the plan, and every query starts from the plan of the current book |

Money tables carry a `book_id` (`CLAUDE.md`): lines carry it through their plan, and no query reads lines without the plan's `book_id` condition. If the owner prefers a `book_id` on the lines too, it is one more column and index in T2.

Migrations: two, one schema change each (`create_budget_plans_table`, `create_budget_plan_lines_table`), dated after spec 007's. No data migration (there is no data to keep). `ledger:sync-categories` is not involved.

## Actions

| Action | Input | Rules enforced | Transaction? | Events |
|---|---|---|---|---|
| `SumByLeaf` | `Book`, `SummaryPeriod` | the live entries of the book in the period, totals and counts per leaf with the category type | no | none |
| `ShowBudget` | `Book`, `BudgetMonth` | actual as spec 007 counts it (K3, K4), percent and status (K5), remaining and off-plan (R8, R9), nulls of a month with no plan (R10), `days_left` (K11) | no | none |
| `SaveBudgetPlan` | `Book`, `NewBudgetPlan`, the saving `User` | category rules (K8): unknown, leaf, not expense, overlap, new retired; replaces the month | yes | none |
| `CopyBudgetPlan` | `Book`, `BudgetMonth`, `bool $replace`, the saving `User` | previous month has a plan; target empty unless `replace`; retired lines skipped (R14); calls `SaveBudgetPlan` | yes (its own, with the save inside) | none |

`SaveBudgetPlan` and `ShowBudget` are separate: the controller answers a `PUT` by calling the save, then `ShowBudget`, so the answer has exactly the shape of a `GET`.

Support objects: `BudgetMonth` (readonly: `first`, `last` as `Y-m-d`, `key` as `Y-m`; `parse(string)`, `previous()`, `current(Book)`), `LeafTotal` (readonly: `categoryId`, `type`, `total`, `entries`), `BudgetLine`, `Budget` (what `BudgetResource` sends), `NewBudgetPlan`, `PlanLineInput` (`category` code, `planned`).

### Category rules of `SaveBudgetPlan` (in this order, per line, first failure wins)

All errors are `FieldError::of('lines.<index>.category', 'lines.category.<code>')`, so the key names the line and the code is stable (see `ValidationCodes`).

1. Code not in `categories` → `not_found`.
2. Level is `leaf` → `not_plannable`.
3. Type is not `expense` → `not_expense`.
4. Its ancestor, or one of its descendants, is also the category of another line → `overlaps`, reported on the descendant's line.
5. Retired and not already a line of the month's current plan → `retired`.

Duplicates (same code twice) are caught earlier by the `distinct` rule of the form request (`lines.category.distinct`), so rules 1–5 see unique codes.

## Endpoints

| Method | Path | Request | Response | Policy | Error codes |
|---|---|---|---|---|---|
| GET | `/books/{book}/budgets/{month}` | `ShowBudgetRequest` | `BudgetResource` | `RequireBook` | `auth.unauthenticated`, `book.not_found`, `validation.failed` |
| PUT | `/books/{book}/budgets/{month}` | `SaveBudgetRequest` | `BudgetResource` | `RequireBook`, `ledger-writes` | the same, plus `request.throttled` |
| POST | `/books/{book}/budgets/{month}/copy` | `CopyBudgetRequest` | `BudgetResource` | `RequireBook`, `ledger-writes` | the same, plus `budget.not_empty`, `budget.nothing_to_copy` |

`SaveBudgetRequest` rules (REQ-2.1–2.8, R4, R5):

| Field | Rules |
|---|---|
| `month` (path) | `date_format:Y-m`, `after_or_equal:2000-01`, `before_or_equal:<current month + 12>` (K7) |
| `expected_income` | `nullable`, `integer:strict`, `min:0`, `max:999999999999` |
| `planned_saving` | `nullable`, `integer:strict`, `min:0`, `max:999999999999` |
| `lines` | `present`, `array`, `max:100` |
| `lines.*.category` | `bail`, `required`, `string`, `distinct` |
| `lines.*.planned` | `bail`, `required`, `integer:strict`, `min:0`, `max:999999999999` |

`max:100` only bounds the payload (the catalogue has 11 tops and 33 groups, 44 possible lines). `CopyBudgetRequest`: `month` as above, `replace` `nullable`, `boolean` (strict JSON boolean). Strings are trimmed and an empty string counts as missing, as in spec 006.

`BudgetController` stays thin: validate, `ShowBudget`/`SaveBudgetPlan`/`CopyBudgetPlan`, `ShowBudget` again for the answer, `BudgetResource`.

## Authorization

`RequireBook` is the only check (K12, R16): the book's account or any live member of its household. A book you may not use is `404 book.not_found`, before the month is looked at. A plan is always read and written through the current book's id, so another book's plan is unreachable (REQ-0.4).

## Error codes

- `book.not_found` (404), `auth.unauthenticated` (401), `request.throttled` (429), existing.
- `budget.not_empty` (409) — copy target already has a plan and `replace` is not true.
- `budget.nothing_to_copy` (409) — the previous month has no plan.
- Field codes: see [contract.md](./contract.md#field-codes).

## Docs

- `contract.md` (REQ-7.1).
- Postman (REQ-7.2): folder "Budget (spec 008)" with "Read a month", "Save a plan", "Clear a plan", "Copy last month" and "Copy last month, replacing"; new variables `budget_month`, `budget_category` (a group code), `budget_planned`, `budget_expected_income`, `budget_planned_saving`.
- `CLAUDE.md` (REQ-7.3): "Current state" lists the budget; the module table's `Ledger` row adds `budget_plans`, `budget_plan_lines`.
- README (REQ-7.3): a "Budget" section.

## Requirement coverage

| REQ | Covered by | Test |
|---|---|---|
| 0.1–0.4 | `RequireBook`, K12 | `BudgetAccessTest` |
| 1.1, 1.2 | K7, `BudgetMonth` | `BudgetMonthTest` (dataset) |
| 2.1, 2.2, 2.9, 2.10 | `SaveBudgetPlan`, K1, K10 | `SaveBudgetPlanTest`, `BudgetConcurrencyTest` |
| 2.3–2.7 | category rules of `SaveBudgetPlan`, `distinct` | `BudgetLineCategoryTest` |
| 2.8 | `SaveBudgetRequest` | `SaveBudgetRequestTest` (dataset) |
| 2.11, 5.6 | `ledger-writes` | `BudgetThrottleTest` |
| 3.1, 3.2, 3.6, 3.7 | `ShowBudget`, `BudgetResource` | `ShowBudgetTest` |
| 3.3 | `SumByLeaf`, K4 | `BudgetActualTest` (deleted, other book, other month, timezone boundary, recurring-rule entry) |
| 3.4 | K5 | `BudgetStatusTest` (dataset around 84/85/100/101 %, planned 0) |
| 3.5 | `ShowBudget` | `ShowBudgetTest` |
| 3.8 | `ShowBudget` | `BudgetRetiredCategoryTest` |
| 4.1–4.2 | `ShowBudget` | `BudgetRemainingTest` (overspent line does not offset, off-plan subtracted, clamp) |
| 4.3 | K11 | `BudgetDaysLeftTest` |
| 5.1–5.5 | `CopyBudgetPlan` | `CopyBudgetPlanTest` |
| 6.1 | K2, K3 | `ShowBudgetTest` (query count) |
| 6.2 | K5, K6 | `BudgetStatusTest`, `BudgetActualTest` (exact integers, near the maximum) |
| 6.3 | K3 | spec 007's tests, unchanged |
| 6.4 | — | `NoPersonalDataInLogsTest` extended with a budget save |
| 7.1–7.3 | docs | reading; the collection is valid JSON; `route:list` |

## Risks / alternatives considered

- **Reading the budget through `SummarizePeriod`** (five queries and a name lookup that the budget does not use): rejected for K3, which shares only the by-leaf query.
- **Storing `actual` on the plan**: would go stale on every edit, delete and recurring entry. Computed on read, as the summary is.
- **PATCH line by line** (B16 chose `PUT`): more routes and partial states; a month's plan is small.
- **A separate `plans` path for writes** (the 2026-11-21 sketch): rejected for R1; one path means one answer shape and one set of month rules.
- **A `version` for concurrent edits**: declined by the owner (B18). The cost is that a member can overwrite another's edit unseen; the transaction keeps each save whole.
- **Percent as a decimal**: the app would have to round; an integer floor is exact and matches the status rule.
- **`book_id` on the lines**: left to the plan row; see "Data".
