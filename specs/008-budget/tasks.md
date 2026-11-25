# 008 — Budget: Tasks

> Status: Done (2026-11-25)
> Requirements: [requirements.md](./requirements.md) · Design: [design.md](./design.md) · Contract: [contract.md](./contract.md)

Order: migrations/models → shared query → actions → controllers/requests/resources → routes → docs.
Each task: tests first, then code, then `php artisan test`, Pint and Larastan. Each is about 100 lines of diff, 200 at most. One commit per PR at the end (squashed), `feat(ledger): monthly budget [spec 008]`.

- [x] **T1 — Plans table and model** (REQ-2.1, 2.10)
  - Migration `create_budget_plans_table` (unique `(book_id, month)`), `BudgetPlan`, `BudgetPlanFactory`.
  - Verify: `BudgetPlanSchemaTest` (a second plan for the same book and month is refused by the database; keys are `ascii_bin` uuids; a book's plan is reached only through its `book_id`).
- [x] **T2 — Lines table and model** (REQ-2.1, 2.5)
  - Migration `create_budget_plan_lines_table` (unique `(budget_plan_id, category_id)`), `BudgetPlanLine`, `BudgetPlanLineFactory`, relations on both models.
  - Verify: `BudgetPlanSchemaTest` (a category twice in one plan is refused by the database; restrict on delete).
- [x] **T3 — Extract the by-leaf query** (REQ-6.1, 6.3)
  - `SumByLeaf` and `LeafTotal`; `SummarizePeriod` calls them. No behaviour change.
  - Verify: the spec 007 tests pass unchanged; `SumByLeafTest` (live entries of the book in the period only, type per leaf, totals as integers).
- [x] **T4 — Month** (REQ-1.1, 1.2)
  - `BudgetMonth` (`parse`, `first`, `last`, `previous`, `current(Book)`), `ValidatesBudgetMonth` rules.
  - Verify: `BudgetMonthTest` (valid month, malformed, `1999-12`, current month + 12 and + 13 in the book's timezone, a UTC day that is already next month in the book's zone).
- [x] **T5 — Show a budget: lines** (REQ-3.1–3.4, 3.7, 3.8, 6.2)
  - `BudgetStatus`, `BudgetLine`, `Budget`, `ShowBudget` for lines: actual by climbing `parent_id`, percent, status, tree order, retired flag.
  - Verify: `BudgetActualTest` (deleted entry, other book, other month, boundary days, recurring-rule entry, group and top lines, income entries never counted), `BudgetStatusTest` (84/85/100/101 %, planned 0, amounts near the maximum), `BudgetRetiredCategoryTest`.
- [x] **T6 — Show a budget: totals** (REQ-3.5, 3.6, 4.1–4.3, 6.1)
  - `ShowBudget` for income, saving, `expenses`, no-plan nulls, `days_left`.
  - Verify: `BudgetRemainingTest` (overspent line does not offset, off-plan subtracted, clamp at 0, no entries), `BudgetDaysLeftTest` (last day gives 1, past and future months give null, the book's timezone), `ShowBudgetTest` (no plan, income and saving, query count).
- [x] **T7 — Save a plan** (REQ-2.1–2.7, 2.9, 2.10)
  - `NewBudgetPlan`, `PlanLineInput`, `SaveBudgetPlan` with the category rules and the transaction.
  - Verify: `SaveBudgetPlanTest` (replaces lines, income and saving; empty clears; a refused save changes nothing; a failing insert rolls back), `BudgetLineCategoryTest` (unknown, leaf, income, saving, overlap parent and child, new retired refused, kept retired accepted), `BudgetConcurrencyTest` (two saves in turn leave exactly the last).
- [x] **T8 — Copy** (REQ-5.1–5.4)
  - `CopyBudgetPlan`.
  - Verify: `CopyBudgetPlanTest` (copies lines, income and saving; skips retired lines; `budget.not_empty`; `replace`; `budget.nothing_to_copy`; January copies from December of the year before; nothing changes on a `409`).
- [x] **T9 — Read endpoint** (REQ-0.1–0.4, 1.1, 3.1)
  - `ShowBudgetRequest`, `BudgetResource`, `BudgetController@show`, the `GET` route.
  - Verify: `BudgetAccessTest` (401 in cookie and token modes; 404 for another user's, a deleted, a malformed or unknown book; every household member reads; another book's plan is never visible), `ShowBudgetTest` (answer shape).
- [x] **T10 — Save endpoint** (REQ-2.1–2.11, 0.3)
  - `SaveBudgetRequest`, `BudgetController@update`, the `PUT` route with `ledger-writes`.
  - Verify: `SaveBudgetRequestTest` (dataset: missing `lines`, not an array, 101 lines, missing category or planned, string and decimal amounts, negative, above the maximum, income and saving codes, duplicate category, error keys carry the line's position), `BudgetThrottleTest` (the 61st write is `429`, together with spec 006's writes), `BudgetAccessTest` (members write; 401; 404), the answer equals a following `GET`.
- [x] **T11 — Copy endpoint** (REQ-5.1–5.6)
  - `CopyBudgetRequest`, `BudgetController@copy`, the `POST` route with `ledger-writes`.
  - Verify: `CopyBudgetPlanTest` (endpoint: body empty, `replace` not boolean is `422`, `409` codes), `BudgetThrottleTest` (the copy counts), `BudgetAccessTest`.
- [x] **T12 — Logs** (REQ-6.4)
  - Extend `NoPersonalDataInLogsTest` with a save and a copy.
  - Verify: the test.
- [x] **T13 — Docs and Postman** (REQ-7.1–7.3)
  - `CLAUDE.md` (Current state, module table), `README.md` ("Budget"), Postman folder "Budget (spec 008)" and its variables in `local.postman_environment.json`, test script storing nothing new. Tick the spec's statuses to Approved/Done.
  - Verify: reading; the collection and the environment are valid JSON; `route:list --path=api` shows the three routes.

## Deviations from spec
(Record any change made during implementation and when it was approved.)

All made on 2026-11-25 during implementation; none changes the contract. Awaiting the owner's review.

- **T4/K7 — month check order.** `ValidatesBudgetMonth` checks the month in `prepareForValidation` with its own validator, not in `rules()`. A bad month then answers alone (`fields.month`), before any body field, as R18 orders. Codes unchanged.
- **T7/K8, K10 — save locking.** `SaveBudgetPlan` reads the month's plan with `SELECT … FOR UPDATE` inside the transaction (3 attempts on deadlock), then creates it if missing (one retry on the unique index), instead of a bare `updateOrCreate`. Two saves of the same month are serialized, so the "replace lines" step never mixes two saves. The category rules read only the categories named by the lines (one `whereIn` query), not the whole table.
- **T7 — several wrong lines.** Added `FieldError::each()` so one `422` lists every wrong line under its own key, as the contract says.
- **T5 — helpers.** `BudgetPlan::of(Book, BudgetMonth)` (query scoped to the book and month) and `BudgetStatus::of()` / `percentOf()` on the enum hold the status rule of K5.
- **Tests.** `BudgetMonthTest` checks the month through the three routes (written after T9). Cookie-mode cases in `BudgetAccessTest` run on the real clock: Symfony's `Cookie::isCleared()` compares with the real `time()`, so a faked clock made the test browser drop its session. Added `WebBrowser::putJson()` (Identity test support), `LedgerScenario::codeOf()` and `Tests/Support/BudgetScenario`. The log test is in `Ledger/Tests/NoPersonalDataInLogsTest.php`.

## Verification report
| REQ | Verified how | Result |
|---|---|---|
| 0.1–0.4 | `BudgetAccessTest` (401 both modes, 404 cases, members read/save/copy, other book's plan untouched, member who leaves) | Pass |
| 1.1, 1.2 | `BudgetMonthTest` (dataset for the three verbs, book timezone) | Pass |
| 2.1, 2.2, 2.9 | `SaveBudgetPlanTest` (PUT equals GET, replace, clear, refused save, failing insert rolled back) | Pass |
| 2.3–2.7 | `BudgetLineCategoryTest` | Pass |
| 2.8 | `SaveBudgetRequestTest` (dataset) | Pass |
| 2.10 | `BudgetPlanSchemaTest`, `BudgetConcurrencyTest` | Pass |
| 2.11, 5.6 | `BudgetThrottleTest` | Pass |
| 3.1, 3.2, 3.5, 3.6 | `ShowBudgetTest` (exact shape, tree order, no plan, cleared plan, income and saving) | Pass |
| 3.3 | `BudgetActualTest`, `SumByLeafTest` | Pass |
| 3.4 | `BudgetStatusTest` | Pass |
| 3.7, 4.1, 4.2 | `BudgetRemainingTest` | Pass |
| 3.8 | `BudgetRetiredCategoryTest` | Pass |
| 4.3 | `BudgetDaysLeftTest` | Pass |
| 5.1–5.5 | `CopyBudgetPlanTest` | Pass |
| 6.1 | `ShowBudgetTest` (query count) | Pass |
| 6.2 | `BudgetStatusTest`, `BudgetActualTest` (near the maximum) | Pass |
| 6.3 | spec 007 tests unchanged | Pass |
| 6.4 | `NoPersonalDataInLogsTest` (budget save, refused save, copy, 409) | Pass |
| 7.1–7.3 | contract, README "Budget", CLAUDE.md, Postman folder and 5 variables (valid JSON), `route:list` shows 3 routes | Done |

Full suite: 1024 passed. Pint and Larastan: clean.
