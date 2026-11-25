# 008 — Budget: API contract

> Status: Approved (2026-11-25) — implemented
> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).
> Auth: every route below needs a signed-in user, by session cookie (web) or `Authorization: Bearer <token>` (mobile), see [spec 002](../002-accounts/contract.md#modes). Without one: `401 auth.unauthenticated`.
> Access and amounts: as in [spec 006](../006-transactions/contract.md#shared-rules). Categories: codes of [spec 005](../005-categories/contract.md). "Actual" is what [spec 007](../007-summary/contract.md) counts. This spec changes neither of them.

## Shared rules

### What a budget is

The **budget of a month** is the plan the book's users make for a calendar month, shown next to what has actually been spent. It holds:

- a planned amount for some **expense categories** (a top category such as `food`, or a group such as `food.groceries`, never a leaf),
- an expected **income** for the month (one amount),
- a planned **saving** for the month (one amount).

All three routes answer with the same shape, the [Budget](#budget-shape).

### Access

Every route is nested under a book: `/books/{book}/budgets/{month}`. Any member of a household may read, save and copy its book's budget. A book that does not exist, a malformed id, a deleted book and a book you cannot use all answer:

```json
{"error": {"code": "book.not_found"}}
```

Order of checks: `401` → `404 book` → `422` month → `422` body fields → `422` category codes → `409` (copy).

### The month

`{month}` is `YYYY-MM`, a calendar month in the book's timezone. It must be from `2000-01` up to 12 months after the current month. At 2026-11-25 in the book's timezone, `2000-01` to `2027-11` are valid. This holds for `GET`, `PUT` and copy. Past months can be read and saved.

### Throttling

`PUT` and copy count in the same limit as spec 006's writes: **60 per minute per account**. Over it: `429 request.throttled` with `Retry-After`. `GET` uses no extra limit.

### Amounts

Integers in the minor unit of the book's currency (TND: millimes; EUR: cents), from `0` to `999999999999`. Every answer also sends `currency`. `0` is allowed in a plan. Amounts are never negative.

### Budget shape

```json
{
  "data": {
    "month": "2026-11",
    "from": "2026-11-01",
    "to": "2026-11-30",
    "currency": "TND",
    "has_plan": true,
    "days_left": 6,
    "income": {"expected": 2500000, "actual": 2500000},
    "saving": {"planned": 300000, "actual": 100000},
    "expenses": {
      "planned": 1100000,
      "actual": 1212500,
      "off_plan": 12500,
      "remaining": 47500
    },
    "lines": [
      {"category": "food", "planned": 600000, "actual": 540000, "percent": 90, "status": "warning", "retired": false},
      {"category": "home.housing", "planned": 400000, "actual": 400000, "percent": 100, "status": "warning", "retired": false},
      {"category": "transport", "planned": 100000, "actual": 160000, "percent": 160, "status": "over", "retired": false},
      {"category": "entertainment.outings", "planned": 0, "actual": 100000, "percent": null, "status": "over", "retired": false}
    ]
  }
}
```

In the example, the lines plan `1 100 000` and spend `1 200 000`; `12 500` was spent in categories with no line. Only food has something left (`600 000 − 540 000 = 60 000`); the overspent lines add nothing back, and `12 500` off-plan is subtracted: `remaining = 60 000 − 12 500 = 47 500`.

| Field | Meaning |
|---|---|
| `month`, `from`, `to` | The month (`YYYY-MM`) and its first and last day (`YYYY-MM-DD`). |
| `currency` | The book's ISO 4217 code. |
| `has_plan` | `true` when the month has at least one line, an expected income or a planned saving. |
| `days_left` | Days from today to the last day of the month, today included, in the book's timezone (on the last day: `1`). `null` for a past or a future month. The app divides `expenses.remaining` by it for a per-day amount. |
| `income.expected` | The expected income, or `null` (none planned). |
| `income.actual` | The month's income entries, as spec 007 counts them. |
| `saving.planned` | The planned saving, or `null`. |
| `saving.actual` | The month's saving entries. |
| `expenses.planned` | Sum of the lines' `planned`. |
| `expenses.actual` | All the month's expense entries, planned or not. |
| `expenses.off_plan` | Expense entries in categories with no line ("Hors budget"): `expenses.actual` minus the sum of the lines' `actual`. |
| `expenses.remaining` | "Reste à dépenser": the sum over the lines of `max(planned − actual, 0)`, minus `off_plan`, never below `0`. An overspent line never offsets another. |
| `lines[].category` | The code of a top category or a group. |
| `lines[].planned` | The planned amount. |
| `lines[].actual` | The month's expense entries on every leaf under the category. |
| `lines[].percent` | `floor(actual × 100 ÷ planned)`, an integer that may exceed 100. `null` when `planned` is `0`. |
| `lines[].status` | `ok`: below 85 % of the plan. `warning`: 85 % or more, up to and including 100 %. `over`: above 100 %. With `planned` `0`: `over` when `actual` is above `0`, else `ok`. |
| `lines[].retired` | `true` when the category has been retired. The line is kept and counted; it cannot be added to another plan. |

- **Order of `lines`**: the category tree's order (`GET /categories`): top categories by position, each followed by its groups.
- **No plan** (`has_plan: false`): `lines` is `[]`, `income.expected`, `saving.planned`, `expenses.planned`, `expenses.off_plan` and `expenses.remaining` are `null`. The `actual` fields are still sent.
- **No entries**: every `actual` is `0`, every `percent` is `0` (or `null` for a planned `0`), every `status` is `ok`, `expenses.remaining` is the sum of the planned amounts.
- **Watch list ("À surveiller")**: built by the app from `lines`: keep `status` `warning` or `over`, sort by `percent` (largest first; `null` with `over` first), keep the first 3.

## GET books/{book}/budgets/{month}

The plan of the month next to what was spent. Computed on each request.

Request: no body, no query.

Response `200`: the [Budget](#budget-shape).

Errors

| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | no session or token |
| 404 | `book.not_found` | the book does not exist, is deleted, or you may not use it |
| 422 | `validation.failed` | `month` codes below |

## PUT books/{book}/budgets/{month}

Replaces the whole month: lines, expected income and planned saving. What is not sent is cleared. Last save wins when two people save the same month; each save is applied whole.

Request

```json
{
  "expected_income": 2500000,
  "planned_saving": 300000,
  "lines": [
    {"category": "food", "planned": 600000},
    {"category": "home.housing", "planned": 400000},
    {"category": "transport", "planned": 100000}
  ]
}
```

- `lines` must be present; `[]` is allowed.
- `expected_income` and `planned_saving` may be omitted or `null` (none).
- To clear the month, send `{"lines": []}`: the answer has `"has_plan": false`.
- A line's `category` is a top category or a group of type `expense`. No two lines may name the same category, and no line may name the parent (or a child) of another line's category.
- A retired category may stay in a month where it already had a line; it cannot be added.

Response `200`: the [Budget](#budget-shape) of the month after the save.

Errors

| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | no session or token |
| 404 | `book.not_found` | as above |
| 422 | `validation.failed` | field codes below |
| 429 | `request.throttled` | more than 60 writes a minute |

## POST books/{book}/budgets/{month}/copy

Copies the previous month's plan (lines, expected income, planned saving) into `{month}`. Lines on categories retired since are not copied.

Request

```json
{"replace": false}
```

- `replace` is optional (default `false`). With `true`, a plan already in `{month}` is replaced.
- The body may be empty.

Response `200`: the [Budget](#budget-shape) of `{month}` after the copy.

Errors

| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | no session or token |
| 404 | `book.not_found` | as above |
| 422 | `validation.failed` | `month` codes, `replace.boolean` |
| 409 | `budget.nothing_to_copy` | the previous month has no plan |
| 409 | `budget.not_empty` | `{month}` already has a plan and `replace` is not `true` |
| 429 | `request.throttled` | more than 60 writes a minute |

Nothing changes on a `409`.

## Field codes

`422 validation.failed` with `fields`. Keys name the field, with the line's position for lines (`lines.0.category`); codes carry no position (`lines.category.required`), as in spec 001.

| Field | Codes |
|---|---|
| `month` | `month.date_format` (`YYYY-MM`, a real month), `month.after_or_equal` (2000-01), `month.before_or_equal` (current month + 12) |
| `expected_income` | `expected_income.integer` (a JSON integer: `"125"` and `12.5` are refused), `expected_income.min` (0), `expected_income.max` (999999999999) |
| `planned_saving` | `planned_saving.integer`, `planned_saving.min` (0), `planned_saving.max` (999999999999) |
| `lines` | `lines.present`, `lines.array`, `lines.max` (100) |
| `lines.N.category` | `lines.category.required`, `lines.category.string`, `lines.category.distinct` (the same category twice), `lines.category.not_found`, `lines.category.not_plannable` (a leaf), `lines.category.not_expense` (an income or saving category), `lines.category.overlaps` (on the line of the child when another line has its parent), `lines.category.retired` (a retired category new to the month) |
| `lines.N.planned` | `lines.planned.required`, `lines.planned.integer`, `lines.planned.min` (0), `lines.planned.max` (999999999999) |
| `replace` | `replace.boolean` |

Field checks run first; the category checks (`not_found` … `retired`) run once the fields are well formed, so an answer may hold the field codes only. When several lines are wrong, the answer lists each one under its own key.

Example

```json
{
  "error": {
    "code": "validation.failed",
    "fields": {
      "lines.1.category": ["lines.category.not_plannable"],
      "lines.2.planned": ["lines.planned.min"]
    }
  }
}
```
