# 007 — Summary: Tasks

> Status: Approved (2026-11-22), with the design, when the owner asked to do all the work and open the PR for review
> Requirements: [requirements.md](./requirements.md) · Design: [design.md](./design.md) · Contract: [contract.md](./contract.md)

Each task: tests first, then code, then `php artisan test`, Pint and Larastan.

- [x] **T1 — Period** (REQ-1.1–1.4, 5.2)
  - `SummaryPeriod`, `WithinOneYear`, `SummaryRequest`.
  - Verified by: `SummaryPeriodTest` (default month in the book's timezone, one month, custom range, field codes).
- [x] **T2 — Totals, days and months** (REQ-1.5–1.7, 4.1, 4.2, 5.1)
  - `TypeTotals`, `Summary`, `SummarizePeriod` (queries 3), `SummaryResource`, `SummaryController`, route.
  - Verified by: `SummaryTotalsTest` (totals and balance, only live entries of the book in the period, empty period, days, months, fixed query count).
- [x] **T3 — By category** (REQ-2.1–2.3)
  - `CategoryTotal`, `OtherNameTotal`; queries 1, 4 and 5.
  - Verified by: `SummaryByCategoryTest` (tree, sums, order, retired, "Other" names).
- [x] **T4 — By member** (REQ-3.1, 3.2)
  - `MemberTotal`; query 2; `FindAccountNames`.
  - Verified by: `SummaryByMemberTest` (household book, order, former member, rule author).
- [x] **T5 — Access** (REQ-0.1–0.3)
  - Verified by: `SummaryAccessTest` (401 in both modes, 404 for every unusable book, every member).
- [x] **T6 — Contract, docs, Postman** (REQ-6.1–6.4)
  - `contract.md`, `CLAUDE.md`, `README.md`, Postman folder and variables.
  - Verified by: reading; the collection is valid JSON; `route:list`.

Deviations:

- T1–T5 were written together (one action, one endpoint); each task's tests are their own file.
- On 2026-11-23 the action, controller, resource, route, tests, docs and Postman folder were missing from the working tree although ticked; only the Support objects, `SummaryRequest` and `WithinOneYear` remained. They were rewritten from this spec and verified again. Test helpers `LedgerScenario::summaryUrl` and `LedgerScenario::spend` were added.
- The query-count test makes one warm-up request first, since the first authenticated request also touches the token.
