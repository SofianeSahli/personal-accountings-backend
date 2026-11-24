# 007 — Summary: Requirements

> Status: Approved (2026-11-22), with readings R1–R12, when the owner asked to do all the work and open the PR for review
> Scope: `app/Modules/Ledger` (one read endpoint, its action, request and resource; no new table). In `Identity`, only a call to the existing `FindAccountNames`. Also `CLAUDE.md`, the README, `contract.md` and the Postman collection.

## Context

Spec 006 records the money: entries in a book, and recurring rules that make them. This spec answers the question the app exists for: **where does the money go?** One endpoint returns, for a period, the totals per type, the balance, and the same totals broken down by category, by member, by day and by month. The app draws its screens and charts from it.

The summary is computed in SQL on each request (the roadmap: "computed in SQL, not in PHP collections"). Nothing is stored, so it never goes stale after an edit.

### Owner decisions

Given in the question dialog on 2026-11-22. Not to be reopened:

| # | Topic | Decision |
|---|---|---|
| S1 | Period | A calendar month (`month=YYYY-MM`, default: this month in the book's timezone), or a custom range `from`–`to` of at most one year. |
| S2 | Breakdowns | Totals per type, always. Then by category tree (top → group → leaf), by member, by day and by month. |
| S3 | Balance | `balance = income − expense − saving`. Savings are also shown on their own. |
| S4 | "Other" entries | The "Other" leaf has its own line, and inside it the names typed, each with its total. Names that differ only in letter case count as one. |

### Readings of the decisions (confirm at approval)

| # | Point | Reading used |
|---|---|---|
| R1 | Which entries count | The book's live entries whose `date` is in the period, both days included. Deleted entries never count. Entries dated in the future (allowed up to one year ahead by spec 006) count in the period of their date. Future occurrences of recurring rules are not projected: only entries that exist count. |
| R2 | Period rules | `month` and `from`/`to` are never sent together (`month.prohibits`). `from` and `to` come together (`from.required_with`, `to.required_with`). `to` is on or after `from`, and before `from` + 1 year (`to.within_one_year`): 2026-01-01 → 2026-12-31 is allowed, 2026-01-01 → 2027-01-01 is not. Dates from `2000-01-01` (months from `2000-01`), as for entries. |
| R3 | Answer shape | Every line holds `income`, `expense`, `saving`, `balance` and `entries` (the number of entries). A category line holds its own `type`, `total` and `entries`, since a category has one type. All amounts are integers in the book currency's minor unit, sent once with `currency`. |
| R4 | Category tree | Only categories with at least one entry in the period appear. A group's and a top's total is the sum of what lies under it. Each level is ordered as in the category tree (`GET /categories`). Retired categories count like the others. |
| R5 | "Other" names | Listed only on "Other" leaves (`is_other`). Grouped ignoring letter case, accents kept apart ("café" and "cafe" are two names), shown as most recently typed, as in spec 006's suggestions. Ordered by total, largest first, then by name. |
| R6 | Members | One line per account that recorded entries in the period (`created_by`, so entries made by a rule count for the rule's author). An account that has left the household keeps its line and its name. In a personal book there is one line. Ordered by expense, largest first, then by account id. |
| R7 | Days and months | Only days and months that have entries appear, oldest first. A month line covers only the part of the month inside the period. |
| R8 | Empty period | `200` with zero totals and empty lists. |
| R9 | Who may read | Anyone who may use the book (spec 006 REQ-0.1, 0.2): its account, or any live member of its household. The summary shows every member's entries, as the entry list already does. |
| R10 | Cost | A fixed number of queries per request, whatever the number of entries. No cache, no stored totals. Reads use no extra rate limit, as in spec 006. |
| R11 | Totals size | A total is a JSON integer. With spec 006's largest amount (999 999 999 999), a total passes JavaScript's safe-integer limit only after more than 9 000 maximal entries in one period, which the write limit and real use make unreachable. No check is added. |
| R12 | Period in the answer | The answer repeats the period it covers (`from`, `to`), so the app can title the screen and chart the days. |

## User stories

- **US-1** — As a member of a book, I want to see how much came in, went out and was saved this month, and what is left, so that I know where we stand.
- **US-2** — As a member of a book, I want to see the same totals by category, and drill from a top category down to its leaves, so that I see where the money goes.
- **US-3** — As a member of a household's book, I want to see the totals of each person, so that we see who recorded what.
- **US-4** — As a member of a book, I want the totals per day and per month, so that the app can chart how they change.
- **US-5** — As a member of a book, I want to see the names typed under "Other", each with its total, so that "Other" does not hide where the money went.
- **US-6** — As a member of a book, I want any range up to a year (a quarter, a year, pay day to pay day), so that I am not stuck with calendar months.

## Acceptance criteria (EARS)

### Access (all stories)

- **REQ-0.1** IF the request has no valid session or token THEN THE SYSTEM SHALL answer `401 auth.unauthenticated`, in cookie and token modes.
- **REQ-0.2** IF the book does not exist, is deleted, has a malformed id, or the caller may not use it THEN THE SYSTEM SHALL answer `404 book.not_found`, the same answer in every case.
- **REQ-0.3** WHEN a live member of the household (`owner` or `member`) asks for the summary of the household's book THE SYSTEM SHALL answer it (R9).

### US-1, US-6 — Period and totals

- **REQ-1.1** WHEN `GET /api/v1/books/{book}/summary` is called without a period THE SYSTEM SHALL summarize the current calendar month in the book's timezone.
- **REQ-1.2** WHEN `month=YYYY-MM` is sent THE SYSTEM SHALL summarize that month, from its first to its last day.
- **REQ-1.3** WHEN `from` and `to` are sent THE SYSTEM SHALL summarize that range, both days included.
- **REQ-1.4** IF the period is malformed or breaks R2 THEN THE SYSTEM SHALL answer `422 validation.failed` with the field codes of the contract, and summarize nothing.
- **REQ-1.5** THE SYSTEM SHALL return the period (`from`, `to`), the book's `currency`, and totals for `income`, `expense` and `saving`, the `balance` (S3) and the number of entries (R3, R12).
- **REQ-1.6** THE SYSTEM SHALL count only the book's live entries dated in the period (R1); entries of other books, deleted entries and entries outside the period never count.
- **REQ-1.7** IF the period has no entries THEN THE SYSTEM SHALL answer `200` with zero totals and empty lists (R8).

### US-2, US-5 — By category

- **REQ-2.1** THE SYSTEM SHALL return the categories with entries in the period as a tree: tops, their groups, their leaves, each with its type, total and number of entries, a parent's total being the sum of its children (R4).
- **REQ-2.2** THE SYSTEM SHALL order each level as in the category tree, and SHALL include retired categories.
- **REQ-2.3** WHEN a leaf is an "Other" leaf THE SYSTEM SHALL list, inside it, the names typed with each name's total and number of entries, grouped and ordered as R5 says.

### US-3 — By member

- **REQ-3.1** THE SYSTEM SHALL return, for each account that recorded entries in the period, its id, its name and its totals per type, balance and number of entries, ordered as R6 says.
- **REQ-3.2** WHEN an account has left the household THE SYSTEM SHALL keep its line and its name.

### US-4 — By day and by month

- **REQ-4.1** THE SYSTEM SHALL return, for each day of the period that has entries, oldest first, the totals per type, balance and number of entries.
- **REQ-4.2** THE SYSTEM SHALL return the same per month (`YYYY-MM`), each month limited to the days inside the period (R7).

### Non-functional

- **REQ-5.1** THE SYSTEM SHALL compute the summary in SQL with a fixed number of queries per request, whatever the number of entries (R10).
- **REQ-5.2** THE SYSTEM SHALL compute every date in the book's timezone and every amount as an exact integer.

### Docs

- **REQ-6.1** `contract.md` SHALL describe the endpoint, its query, its answer and its error codes.
- **REQ-6.2** The Postman collection SHALL get a "Summary (spec 007)" folder with a request per way of choosing the period, and the variables they use.
- **REQ-6.3** `CLAUDE.md` SHALL list the summary endpoint in "Current state", and the module table SHALL say the summary has no table.
- **REQ-6.4** The README SHALL describe the summary.

Checklist — have these been covered?
- [x] Validation errors (per field, with stable codes) — REQ-1.4 (R2)
- [x] Unauthenticated access (401), cookie and token modes — REQ-0.1
- [x] Access to another household's data — REQ-0.2, 0.3, 1.6
- [x] Rate limits / repeated requests / duplicates — R10 (a read; no extra limit)
- [x] Money edge cases (zero, negative, currency exponent) — REQ-1.5, 1.7, 5.2 (R3, R11)
- [x] Date & timezone edge cases (household timezone, month boundaries) — REQ-1.1–1.3, 4.2, 5.2 (R2, R7)
- [x] Deleting / changing referenced data — REQ-1.6, 2.2, 3.2 (R1, R4, R6)

## Non-goals

- Budgets, targets and alerts.
- Comparing with the previous period, averages and forecasts.
- Projecting future occurrences of recurring rules.
- Summaries across several books, and converting currencies.
- Filters inside the summary (one member, one category): the entry list of spec 006 already filters.
- Stored or cached totals, and exports (CSV, PDF).
- The frontend.

## Open questions

None left from the dialog. Please confirm the readings R1–R12 at approval.
