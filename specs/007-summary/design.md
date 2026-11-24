# 007 — Summary: Design

> Status: Approved (2026-11-22), when the owner asked to do all the work and open the PR for review
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Everything lives in `Ledger`. One route, `GET /api/v1/books/{book}/summary`, behind the existing `RequireBook` middleware (REQ-0.1–0.3). A Form Request turns the query into a `SummaryPeriod`. The action `SummarizePeriod` (the name the roadmap gave it) runs five grouped queries and assembles a readonly `Summary`, which `SummaryResource` sends.

No table, no migration: the summary is computed from `transactions` and `categories` on each request. `Household` does not change; `Identity` is called through `FindAccountNames`.

## Key decisions

| # | Decision | Why |
|---|---|---|
| K1 | Five queries, each grouped in SQL, whatever the number of entries: by leaf; by author and type; by day and type; by "Other" leaf and name; and the category list. Month lines and the type totals are sums of the day lines, made in PHP from at most 366 × 3 rows | REQ-5.1, R10 |
| K2 | The type of an entry is read by joining `categories` (`categories.type`, set on every level by spec 005). The join stays inside `Ledger` | REQ-1.5, 3.1, 4.1 |
| K3 | The category tree is built in PHP: the leaf totals of query 1 climb their `parent_id` chain. The whole `categories` table is read once (a few hundred rows), so retired categories are found too | REQ-2.1, 2.2 |
| K4 | "Other" names are grouped in SQL by `other_name COLLATE utf8mb4_bin` (exact spelling, accents kept), with `ANY_VALUE(other_name)` and `MAX(updated_at)`. PHP then merges spellings that differ only in case (`mb_strtolower`), keeping the most recently typed one, like spec 006 K14. The column's own collation is accent-insensitive and would merge "café" and "cafe" | REQ-2.3, R5 |
| K5 | `SUM` comes back from MySQL as a decimal string; it is cast to `int` once, in the action | REQ-5.2, R11 |
| K6 | The period check that needs two fields (`to` before `from` + 1 year) is a named rule class, `WithinOneYear`, so its code is `to.within_one_year`. `month` uses Laravel's `prohibits:from,to` (`month.prohibits`), the range `required_with` | REQ-1.4, R2 |
| K7 | The default month is computed from `BookDay::today($book)`; month ends use Carbon's `endOfMonth()` on a UTC midnight date, so no timezone leaks into date maths (spec 006 K16) | REQ-1.1, 1.2, 5.2 |
| K8 | Names come from one `FindAccountNames` call for the member lines | REQ-3.1, 3.2 |

## Placement

| Piece | Path |
|---|---|
| `SummaryPeriod`, `TypeTotals`, `CategoryTotal`, `OtherNameTotal`, `MemberTotal`, `Summary` | `app/Modules/Ledger/Support/` |
| `SummarizePeriod` | `app/Modules/Ledger/Actions/` |
| `WithinOneYear` | `app/Modules/Ledger/Rules/` |
| `SummaryRequest` | `app/Modules/Ledger/Controllers/Requests/` |
| `SummaryResource` | `app/Modules/Ledger/Controllers/Resources/` |
| `SummaryController` (`show`) | `app/Modules/Ledger/Controllers/` |
| Tests | `app/Modules/Ledger/Tests/Summary/` |

## Support objects

- `SummaryPeriod` (readonly): `from`, `to` (`Y-m-d`). `month(string $month)`, `range(string $from, string $to)`, `thisMonth(Book $book)`. (REQ-1.1–1.3)
- `TypeTotals` (readonly): `income`, `expense`, `saving`, `entries`; `balance()` = income − expense − saving; `plus(CategoryType, int $amount, int $entries)` returns a new object; `none()`. (S3, REQ-1.5)
- `CategoryTotal` (readonly): `code`, `type`, `total`, `entries`, `children` (list of `CategoryTotal`), `otherNames` (list of `OtherNameTotal`). (REQ-2.1–2.3)
- `OtherNameTotal` (readonly): `name`, `total`, `entries`.
- `MemberTotal` (readonly): `accountId`, `name`, `totals`. (REQ-3.1)
- `Summary` (readonly): `period`, `currency`, `totals`, `categories`, `members`, `days` (array `Y-m-d` → `TypeTotals`), `months` (array `Y-m` → `TypeTotals`).

## Action `SummarizePeriod`

`handle(Book $book, SummaryPeriod $period): Summary`. The base query is `Transaction::query()` (soft-delete scope) with `book_id = book` and `date BETWEEN from AND to` (REQ-1.6), which the index `(book_id, deleted_at, date, …)` of spec 006 serves.

1. **By leaf**: `category_id, COUNT(*), SUM(amount)` grouped by `category_id`.
2. **By author and type**: joined to `categories`, `created_by, categories.type, COUNT, SUM` grouped by both.
3. **By day and type**: `date, categories.type, COUNT, SUM` grouped by both, ordered by date. Days come from it; months and the type totals add its rows up.
4. **"Other" names**: `other_name IS NOT NULL`, grouped by `category_id, other_name COLLATE utf8mb4_bin` (K4).
5. **Categories**: `Category::query()->get()` keyed by id, ordered by `position`.

The tree: each leaf row adds its total to the leaf, its group and its top (K3); each level is then sorted by `position` (REQ-2.2). Members are sorted by expense (descending), then account id (R6).

## Endpoint

`GET /api/v1/books/{book}/summary` → `SummaryController@show`: `SummaryRequest` validates, `$request->period($current->book)` builds the period, `SummarizePeriod` runs, `SummaryResource` answers. No policy beyond `RequireBook`: reading the summary is "using the book" (R9), like the entry list.

`SummaryRequest` rules (REQ-1.4, R2):

| Field | Rules |
|---|---|
| `month` | `nullable`, `date_format:Y-m`, `after_or_equal:2000-01`, `prohibits:from,to` |
| `from` | `bail`, `nullable`, `required_with:to`, `date_format:Y-m-d`, `after_or_equal:2000-01-01` |
| `to` | `bail`, `nullable`, `required_with:from`, `date_format:Y-m-d`, `after_or_equal:from`, `WithinOneYear` |

The answer shape is in [contract.md](./contract.md). Every node of the category tree has `children` and `other_names` (empty when there are none), so the app reads one shape at every level.

## Error codes

No new error code beyond the field codes: `month.date_format`, `month.after_or_equal`, `month.prohibits`, `from.required_with`, `from.date_format`, `from.after_or_equal`, `to.required_with`, `to.date_format`, `to.after_or_equal`, `to.within_one_year`. Plus `401 auth.unauthenticated` and `404 book.not_found` from the existing middleware.

## Docs

- `contract.md` (REQ-6.1).
- Postman (REQ-6.2): folder "Summary (spec 007)" with "This month", "One month" and "Custom range"; new variables `summary_month`, `summary_from`, `summary_to`.
- `CLAUDE.md` (REQ-6.3): "Current state" lists the summary; the module table says the summaries are computed on read; spec 007 no longer "comes next".
- README (REQ-6.4): a "Summary" section.

## Requirement coverage

| REQ | Covered by | Test |
|---|---|---|
| 0.1, 0.2, 0.3 | `RequireBook` | `SummaryAccessTest` |
| 1.1–1.3 | `SummaryRequest::period`, `SummaryPeriod` | `SummaryPeriodTest` |
| 1.4 | `SummaryRequest`, `WithinOneYear` | `SummaryPeriodTest` (dataset) |
| 1.5–1.7 | `SummarizePeriod`, `TypeTotals` | `SummaryTotalsTest` |
| 2.1–2.3 | `SummarizePeriod` (tree, names) | `SummaryByCategoryTest` |
| 3.1, 3.2 | `SummarizePeriod`, `FindAccountNames` | `SummaryByMemberTest` |
| 4.1, 4.2 | `SummarizePeriod` (days, months) | `SummaryTotalsTest` |
| 5.1 | K1 | `SummaryTotalsTest` (query count) |
| 5.2 | K5, K7 | `SummaryPeriodTest`, `SummaryTotalsTest` |
| 6.1–6.4 | docs | reading; the collection is valid JSON; `route:list` |

## Risks / alternatives considered

- **Stored monthly totals** (a `summaries` table updated on each write): faster on very large books, but every edit, delete, recurring entry and category change would have to update it, and a missed update gives wrong numbers. With the date index, five grouped queries over one book's year are cheap. Revisit only when a spec measures a need.
- **Grouping months in SQL** (`DATE_FORMAT`): one more query for numbers PHP gets from the day rows already read.
- **A separate `Reporting` module**: CLAUDE.md creates one only when summaries outgrow `Ledger`. One action and one endpoint do not.
