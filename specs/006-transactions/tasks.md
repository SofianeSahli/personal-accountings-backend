# 006 — Transactions and recurring rules: Tasks

> Status: Approved (2026-11-22), with the design, when the owner asked to do all the remaining work and open the PR for review
> Requirements: [requirements.md](./requirements.md) · Design: [design.md](./design.md) · Contract: [contract.md](./contract.md)

Each task: tests first, then code, then `php artisan test`, Pint and Larastan.

- [x] **T1 — Tables, models, factories** (REQ-6.1–6.3)
  - Migrations for `recurring_rules` (+ `frequency` CHECK) and `transactions`; `Transaction`, `RecurringRule`, `Frequency`; `TransactionFactory`, `RecurringRuleFactory`; `LedgerScenario` test helper.
  - Verified by: `TransactionIntegrityTest` (FKs restrict, unique `(recurring_rule_id, rule_due_on)`, frequency CHECK).
- [x] **T2 — Dates and schedule** (REQ-4.3, 6.5)
  - `BookDay`, `Schedule`.
  - Verified by: `ScheduleTest` (weekly, monthly, yearly, intervals, month ends, 29 February, `ends_on`, `firstOnOrAfter`, and `BookDay` in timezones ahead of and behind UTC).
- [x] **T3 — Category choice** (REQ-0.4, R3, R4)
  - `CategoryChoice`, `ChosenCategory`, `ChooseCategory`.
  - Verified by: the field datasets of `RecordTransactionTest`, `UpdateTransactionTest`, `CreateRecurringRuleTest` and `ManageRecurringRulesTest`.
- [x] **T4 — Record, read, list entries** (REQ-0.1–0.7, 1.1–1.4, 2.1–2.5)
  - `NewTransaction`, `TransactionFilters`, `TransactionPage`, `RecordTransaction`, `ListTransactions`; policy, requests, resources, `TransactionController` (index, store, show); `LedgerWriteLimit`; routes.
  - Verified by: `RecordTransactionTest`, `ListTransactionsTest` (list, filters, show), `TransactionAccessTest` (401, 404, member access on every route, write limit).
- [x] **T5 — Edit and delete entries** (REQ-3.1–3.6)
  - `TransactionChanges`, `UpdateTransaction`, `DeleteTransaction`; `update`, `destroy`.
  - Verified by: `UpdateTransactionTest` (edit and delete).
- [x] **T6 — Recurring rules: create, read, list, catch up** (REQ-4.1–4.4, 4.6, 4.8, 4.11)
  - `NewRecurringRule`, `RecurringRulePage`, `CreateRecurringRule`, `CatchUpRecurringRule`, `ListRecurringRules`; policy, requests, resources, `RecurringRuleController` (index, store, show).
  - Verified by: `CreateRecurringRuleTest`, `ManageRecurringRulesTest` (list, show, 404), `TransactionAccessTest` (every rule route).
- [x] **T7 — Recurring rules: edit and delete** (REQ-4.9, 4.10)
  - `RecurringRuleChanges`, `UpdateRecurringRule`, `DeleteRecurringRule`.
  - Verified by: `ManageRecurringRulesTest`.
- [x] **T8 — The job** (REQ-4.5, 4.7, 4.12, 6.6)
  - `RecordDueEntries`, `RecordDueEntriesCommand`, schedule registration; `scheduler` service in `docker-compose.yml`.
  - Verified by: `RecordDueEntriesTest` (due, catch-up, timezone, idempotent, deleted rule, deleted book, ended rule, one failing rule), `NoMoneyDataInLogsTest`, `TransactionIntegrityTest` (unique occurrence).
- [x] **T9 — "Other" suggestions** (REQ-5.1–5.3)
  - `SuggestOtherNames`, `SuggestOtherNamesRequest`, `OtherNameController`, route.
  - Verified by: `SuggestOtherNamesTest`.
- [x] **T10 — Contract, docs, Postman** (REQ-7.1–7.4)
  - `contract.md`, `CLAUDE.md`, `README.md`, Postman folder and variables.
  - Verified by: reading; the collection is valid JSON; `route:list`.

Deviations:

- T4: entry and rule ids in the path are resolved by two small middleware, `RequireTransaction` and `RequireRecurringRule` (same pattern as `RequireBook`), instead of inside the controller. Otherwise the Form Request would validate before the `404`, against the check order `401` → `404` → `422` of spec 003. The policies (K15) are called from these middleware.
- T4: a few readonly result objects were added to `Support/` (`AccountNames`, `TransactionView`, `RecordedTransaction`, `RecurringRuleView`, `DueEntriesReport`, `FieldError`), and two small actions, `NameAccounts` (one `FindAccountNames` call per answer, K13) and `ShowTransaction` / `ShowRecurringRule`, so each controller method still calls one action.
- T4: request field rules shared by entries and rules live in `Controllers/Requests/MoneyRules.php` (like `BookRules`).
- T8: the job test makes one rule fail through its book's invalid timezone, because actions are `final` and cannot be mocked.
- T1: the `due_order` generated column (K10) is part of the `recurring_rules` create migration; the `frequency` CHECK is its own migration.
- Tests: Docker Desktop could not start on the development machine, so Pint and Larastan ran with the host PHP on a copy of the project with its own `vendor/`, and the Pest suite runs in CI (MySQL 8.4) on the pull request.
