# 008 — Budget: Requirements

> Status: Approved (2026-11-25) — implemented
> Scope: `app/Modules/Ledger` (two tables, a read, a save and a copy endpoint, their actions, requests and resource; the by-leaf query of spec 007 is extracted so both specs share it). Also `CLAUDE.md`, the README, `contract.md` and the Postman collection.

## Context

Spec 006 records the money and spec 007 shows where it went. This spec adds the **plan**: what the household intends to spend in each expense category for a month, how much it expects to earn and how much it wants to save. The same read compares the plan with what was actually spent, so the app can show "Reste à dépenser" and a watch list ("À surveiller") of the categories close to or over their plan.

"Actual" is not a new notion: it is the sum of the month's live entries exactly as spec 007 counts them (book timezone, deleted entries excluded, recurring rules only through the entries they have already made).

### Owner decisions

Given on 2026-11-21 (roadmap) and in the question dialog on 2026-11-25. Not to be reopened:

| # | Topic | Decision |
|---|---|---|
| B1 | What a plan is | A monthly plan per book: a planned amount per expense category, the income expected that month, and one planned saving amount. A "copy last month" action exists. |
| B2 | Actual | The sum of the month's live entries per category, as spec 007 counts them. |
| B3 | Status of a line | `ok` below 85 % of the plan, `warning` at 85 % or more, `over` above 100 %. |
| B4 | "Reste à dépenser" | What is left of the plan. An overspent category never offsets another: each planned line contributes `max(planned − actual, 0)`. |
| B5 | Watch list | The planned lines in `warning` or `over`, sorted by % used, at most 3. The backend gives statuses and percentages; the app filters, sorts and caps (see design K9). |
| B6 | Money | Integers in the minor unit of the book's currency, as in specs 006 and 007. |
| B7 | Level of a line (Q1) | A top category or a group, never a leaf. A line on a node and on one of its ancestors is refused. |
| B8 | Types (Q2) | Expense lines, plus one optional planned saving amount. |
| B9 | Expected income (Q3) | One amount for the month. |
| B10 | Where the view lives (Q4) | A new endpoint. The spec 007 contract does not change. |
| B11 | Spending outside the plan (Q5) | Shown on its own ("Hors budget") and subtracted from "Reste à dépenser". |
| B12 | Copy (Q6) | A server endpoint, copying the previous month; refused when the target month already has a plan unless the request says to replace it. |
| B13 | Who edits (Q7) | Any live member of a household's book, as for entries. |
| B14 | Months (Q8) | From 2000-01 to 12 months ahead of the current month; past months are editable. |
| B15 | Retired categories (Q9) | An existing line on one is kept and flagged `retired: true`; a new line cannot use one. |
| B16 | Saving a plan (Q10) | `PUT` of the whole month, an empty plan allowed. |
| B17 | Days left (Q11) | Computed by the backend: `days_left` for the current month, `null` otherwise. |
| B18 | Concurrency (Q12) | Last write wins. No version, no `If-Match`. |

### Readings of the decisions (confirm at approval)

| # | Point | Reading used |
|---|---|---|
| R1 | One resource | The plan and its comparison with actual are one resource, the **budget of a month**: `GET`, `PUT` and `POST …/copy` under `/books/{book}/budgets/{YYYY-MM}`. The 2026-11-21 sketch (`plans/…` for writes) was a starting point; one path and one answer shape is simpler for the app. `PUT` and copy answer with the same shape as `GET`. |
| R2 | Month validation | The month in the path is checked for every verb, against the window of B14: `422 validation.failed` with a `month` code, after the `404 book.not_found` check. Reading outside the window is refused too. |
| R3 | "Has a plan" | `has_plan` is true when the month has at least one line, an expected income or a planned saving. `PUT` with no lines, no income and no saving clears the month; it is the same as having no plan. |
| R4 | PUT body | `lines` must be present (it may be empty). `expected_income` and `planned_saving` may be omitted or `null`, which means "none"; the `PUT` replaces the whole month, so an omitted field clears it. |
| R5 | Amounts | A line's `planned`, `expected_income` and `planned_saving` are integers from `0` to `999999999999` (spec 006's maximum). `0` is allowed: it says "nothing should be spent here". |
| R6 | Percent | `percent` is `floor(actual × 100 ÷ planned)`, an integer that may exceed 100, and `null` when `planned` is 0. The status is decided on exact integers, never on the rounded percent: `warning` when `actual × 100 ≥ planned × 85`, `over` when `actual > planned`. With `planned` 0: `over` when `actual > 0`, else `ok`. |
| R7 | Actual of a line | The expense entries of every leaf under the line's category (the category itself for a group's leaves, all its groups' leaves for a top). Entries on retired categories count. Income and saving entries never count towards an expense line. |
| R8 | Expense totals | `expenses.actual` is all the month's expense entries. `expenses.planned` is the sum of the lines. `expenses.off_plan` is `expenses.actual` minus the actual of all lines: the expenses whose category has no line. |
| R9 | Reste à dépenser | `expenses.remaining = max( Σ max(planned − actual, 0) over the lines − off_plan, 0 )`. It is clamped at 0 so the headline is never negative; the overspending shows in the lines and in `off_plan`. |
| R10 | No plan | When `has_plan` is false: `lines` is empty, `expected` and `planned` are `null`, and `expenses.planned`, `expenses.remaining` and `expenses.off_plan` are `null`. `actual` figures are still sent. |
| R11 | Income and saving | `income.expected` against `income.actual`, `saving.planned` against `saving.actual`, both actuals being the month's income and saving entries. No status or percent on them. |
| R12 | Days left | The days from today to the last day of the month, today included, in the book's timezone (the last day of the month gives `1`). `null` for a past or a future month. |
| R13 | Order of lines | In the order of the category tree (`GET /categories`): tops by position, each followed by its groups. |
| R14 | Copy | Copies the previous month's lines, expected income and planned saving to the target month. Lines on categories retired since are not copied. A previous month with no plan gives `409 budget.nothing_to_copy`. A target month with a plan gives `409 budget.not_empty` unless the body says `"replace": true`. The answer is the target month's budget. |
| R15 | Replacing lines | Lines belong to a plan and are replaced by each `PUT` (the old rows are removed and the new ones written). They are not money records. The plan row of a month is never removed. |
| R16 | Access | Same as spec 006: `RequireBook` is the access check, and any live member may read and write. No other policy. |
| R17 | Throttle | `PUT` and copy share spec 006's write limit (`ledger-writes`, 60 per minute per account). Reads use no extra limit. |
| R18 | Order of checks | `401` → `404 book` → `422` month → `422` body fields → `422` category codes → `409` (copy). |
| R19 | Cost | A fixed number of queries per read, whatever the number of entries (design K2). |

## User stories

- **US-1** — As a member of a book, I want to plan the month (what to spend per category, what I expect to earn, what to save), so that we agree on where the money should go.
- **US-2** — As a member of a book, I want each planned category to show what has been spent, the percentage used and a status, so that I see which ones need care.
- **US-3** — As a member of a book, I want to see what is left to spend, counting spending outside the plan, so that the headline is honest.
- **US-4** — As a member of a book, I want the statuses and percentages in one answer, so that the app can show the few categories to watch.
- **US-5** — As a member of a book, I want to copy last month's plan into this month, so that I do not retype it.
- **US-6** — As a member of a household, I want every member to see and edit the household's plan.

## Acceptance criteria (EARS)

### Access (all stories)

- **REQ-0.1** IF the request has no valid session or token THEN THE SYSTEM SHALL answer `401 auth.unauthenticated`, in cookie and token modes.
- **REQ-0.2** IF the book does not exist, is deleted, has a malformed id, or the caller may not use it THEN THE SYSTEM SHALL answer `404 book.not_found`, the same answer in every case and for every route of this spec.
- **REQ-0.3** WHEN a live member of the household (`owner` or `member`) reads, saves or copies the budget of the household's book THE SYSTEM SHALL do it (B13, R16).
- **REQ-0.4** THE SYSTEM SHALL never read or write the plan of another book.

### Month (all stories)

- **REQ-1.1** IF the month in the path is not `YYYY-MM` of a real month, is before `2000-01`, or is later than the current month in the book's timezone plus 12 months THEN THE SYSTEM SHALL answer `422 validation.failed` with the `month` code of the contract, for `GET`, `PUT` and copy alike (R2, B14).
- **REQ-1.2** THE SYSTEM SHALL compute the current month, and every date, in the book's timezone.

### US-1, US-6 — Save a plan

- **REQ-2.1** WHEN `PUT /api/v1/books/{book}/budgets/{month}` is called with valid data THE SYSTEM SHALL replace the month's lines, expected income and planned saving with the ones sent, and answer `200` with the month's budget (R1, R4).
- **REQ-2.2** WHEN `lines` is empty and `expected_income` and `planned_saving` are absent or `null` THE SYSTEM SHALL clear the month, and `has_plan` becomes `false` (R3).
- **REQ-2.3** IF a line's category is unknown THEN THE SYSTEM SHALL answer `422` with `lines.category.not_found` on that line.
- **REQ-2.4** IF a line's category is a leaf THEN THE SYSTEM SHALL answer `422` with `lines.category.not_plannable`; IF it is not an expense category THEN `lines.category.not_expense` (B7, B8).
- **REQ-2.5** IF two lines name the same category THEN THE SYSTEM SHALL answer `422` with `lines.category.distinct`.
- **REQ-2.6** IF a line's category is an ancestor or a descendant of another line's category THEN THE SYSTEM SHALL answer `422` with `lines.category.overlaps` on the descendant's line (B7).
- **REQ-2.7** IF a line's category is retired and the month did not already have a line on it THEN THE SYSTEM SHALL answer `422` with `lines.category.retired`. A line that was already in the month may be kept (B15).
- **REQ-2.8** IF `planned`, `expected_income` or `planned_saving` is missing where required, not a JSON integer, below 0 or above 999999999999 THEN THE SYSTEM SHALL answer `422` with the field codes of the contract (R5).
- **REQ-2.9** THE SYSTEM SHALL write the plan and its lines in one transaction: a refused or failed save changes nothing.
- **REQ-2.10** WHEN two members save the same month THE SYSTEM SHALL keep the last save, whole, never a mix of the two (B18).
- **REQ-2.11** IF an account makes more than 60 writes (this spec's and spec 006's together) in a minute THEN THE SYSTEM SHALL answer `429 request.throttled` (R17).

### US-2, US-4 — Read the budget

- **REQ-3.1** WHEN `GET /api/v1/books/{book}/budgets/{month}` is called THE SYSTEM SHALL answer `200` with the month, its first and last day (`from`, `to`), the book's `currency` and `has_plan`.
- **REQ-3.2** THE SYSTEM SHALL give, for each line, its category, `planned`, `actual`, `percent`, `status` and `retired`, in the order of R13.
- **REQ-3.3** THE SYSTEM SHALL count `actual` as spec 007 counts entries: the book's live entries dated in the month, in the book's timezone, with deleted entries excluded and recurring rules counted only through the entries they have made (B2, R7).
- **REQ-3.4** THE SYSTEM SHALL set `status` to `ok` below 85 % of the plan, `warning` from 85 % up to and including 100 %, and `over` above 100 %, with `percent` and the planned-0 case as in R6 (B3).
- **REQ-3.5** THE SYSTEM SHALL give the expected income with the month's income `actual`, and the planned saving with the month's saving `actual` (R11).
- **REQ-3.6** WHEN the month has no plan THE SYSTEM SHALL answer `200` with `has_plan: false` and the nulls of R10.
- **REQ-3.7** WHEN the book has no entries in the month THE SYSTEM SHALL answer `200` with every `actual` at `0`, `percent` `0` (or `null` when `planned` is 0) and `status` `ok`.
- **REQ-3.8** THE SYSTEM SHALL keep lines on retired categories, with `retired: true`, and count their entries like the others (B15).

### US-3 — Reste à dépenser

- **REQ-4.1** THE SYSTEM SHALL give `expenses.planned`, `expenses.actual` and `expenses.off_plan` as R8 defines them.
- **REQ-4.2** THE SYSTEM SHALL give `expenses.remaining` as R9 defines it: an overspent line never offsets another, and the off-plan spending is subtracted (B4, B11).

### Days left

- **REQ-4.3** THE SYSTEM SHALL give `days_left` as R12 defines it, and `null` for any month other than the current one in the book's timezone (B17).

### US-5 — Copy last month

- **REQ-5.1** WHEN `POST /api/v1/books/{book}/budgets/{month}/copy` is called and the previous month has a plan THE SYSTEM SHALL write that plan into `{month}` as R14 says and answer `200` with the target month's budget.
- **REQ-5.2** IF the target month already has a plan and the body does not say `"replace": true` THEN THE SYSTEM SHALL answer `409 budget.not_empty` and change nothing; WHEN it says `"replace": true` THE SYSTEM SHALL replace the target month.
- **REQ-5.3** IF the previous month has no plan THEN THE SYSTEM SHALL answer `409 budget.nothing_to_copy` and change nothing.
- **REQ-5.4** THE SYSTEM SHALL not copy lines whose category is retired (B15, R14).
- **REQ-5.5** IF `replace` is present and not a boolean THEN THE SYSTEM SHALL answer `422` with `replace.boolean`.
- **REQ-5.6** The copy counts as a write for the limit of REQ-2.11.

### Non-functional

- **REQ-6.1** THE SYSTEM SHALL read a budget with a fixed number of queries, whatever the number of entries, reusing spec 007's by-category aggregation instead of writing it again (R19).
- **REQ-6.2** THE SYSTEM SHALL keep every amount an exact integer, and compute the percent and the status from integers only (R6).
- **REQ-6.3** THE SYSTEM SHALL leave the contract of spec 007 unchanged; its tests still pass after the shared query is extracted (B10).
- **REQ-6.4** THE SYSTEM SHALL log no amount, category or other personal data of a plan.

### Docs

- **REQ-7.1** `contract.md` SHALL describe every endpoint with request, response, field codes and error codes.
- **REQ-7.2** The Postman collection SHALL get a "Budget (spec 008)" folder with one request per route, and the variables they use.
- **REQ-7.3** `CLAUDE.md` SHALL list the budget in "Current state" and name its tables in the module table; the README SHALL describe it.

Checklist — have these been covered?
- [x] Validation errors (per field, with stable codes) — REQ-1.1, 2.3–2.8, 5.5
- [x] Unauthenticated access (401), cookie and token modes — REQ-0.1
- [x] Access to another household's data — REQ-0.2–0.4
- [x] Rate limits / repeated requests / duplicates — REQ-2.5, 2.10, 2.11, 5.6
- [x] Money edge cases (zero, negative, currency exponent) — REQ-2.8, 3.4, 3.7, 6.2 (R5, R6)
- [x] Date & timezone edge cases (household timezone, month boundaries) — REQ-1.1, 1.2, 3.3, 4.3 (R12)
- [x] Deleting / changing referenced data — REQ-2.7, 3.3, 3.8, 5.4 (a deleted entry stops counting; a retired category keeps its line)

## Non-goals

- Rollover of unspent amounts to the next month.
- Alerts and notifications.
- Yearly budgets.
- Pay-day months (a budget month is a calendar month).
- Provisions and sinking funds.
- Projecting recurring rules into the plan or the actual.
- Budgets across several books, and converting currencies.
- A plan line per leaf, per member or per income category.
- Versioning or conflict detection of concurrent edits (B18).
- The watch list's sorting and cap: the app does them (K9).
- The frontend.

## Open questions

None left from the dialog. Please confirm the readings R1–R19 at approval, in particular R2 (reading is limited to the same month window as writing), R9 (`remaining` clamped at 0) and R14 (copy skips retired lines).
