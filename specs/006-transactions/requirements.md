# 006 — Transactions and recurring rules: Requirements

> Status: Approved (2026-11-22), with readings R1–R16, when the owner asked to do all the remaining work and open the PR for review
> Scope: `app/Modules/Ledger` (`transactions` and `recurring_rules` tables, their actions, routes, policies and the scheduled job). In `Household` and `Identity`, only calls to existing actions (`FindMembership`, `FindAccountNames`). Also `docker-compose.yml` (a scheduler process), `CLAUDE.md`, the README, `contract.md` and the Postman collection.

## Context

Specs 003–005 built the books, the households that share them and the category tree. This spec adds the money itself: **entries** (stored in the `transactions` table) recorded in a book against a leaf category, and **recurring rules** that record the same entry on a schedule (rent, salary, subscriptions). It also adds the list the app reads entries from, with filters, and suggestions for names already typed for "Other".

The monthly summary is spec 007.

Spec 003's decisions D1–D14 and spec 005's C1–C11 stand, except that **E2 below widens D3**: household members now write money, not only read it. Book settings (rename, timezone) stay with the book's manager.

### Owner decisions

Given in the question dialog on 2026-11-22. Not to be reopened:

| # | Topic | Decision |
|---|---|---|
| E1 | Recurring rules | Part of this spec, not a later one. |
| E2 | Who writes | In a household's book, every live member (`owner` or `member`) can record entries and edit or delete **any** entry and any recurring rule. The author is recorded and shown. In a personal book, only its account. |
| E3 | Amount and direction | The amount is always a positive integer in the book currency's minor unit. Whether money goes out or comes in follows the leaf category's type (`expense`, `income`, `saving`). |
| E4 | Entry dates | From `2000-01-01` to today + 1 year, "today" being the date in the book's timezone. |
| E5 | "Other" name | Required, trimmed, 1–60 characters, when the leaf is an "Other" leaf (`is_other`). Refused on any other leaf. |
| E6 | Comment | Optional, trimmed, up to 200 characters. An empty comment is stored as `null`. |
| E7 | List filters | Date range (`from`, `to`), category at any level, type, author. |
| E8 | "Other" suggestions | In this spec: the "Other" names already typed in the same book can be suggested. |
| E9 | Retries | The client may send its own UUID `id` when creating an entry. Sending the same id again to the same book answers the existing entry instead of creating a second one. |
| E10 | Repeat patterns | `weekly`, `monthly` or `yearly`, with an interval "every N" (1–12). A monthly or yearly rule on a day the month lacks (29–31, or 29 February) falls on that month's last day. |
| E11 | When entries are made | On each due date, in the book's timezone, a scheduled job records the entry automatically. If the job missed days, it catches up. An entry never exists before its date. |
| E12 | Backfill | A rule's start date may be up to 1 year in the past. Occurrences from the start date up to today are created right away. |
| E13 | Changing a rule | Editing a rule changes only entries not yet created. Deleting a rule (soft) stops it. Entries already created stay, each editable on its own, and keep their link to the rule. |

### Readings of the decisions (confirm at approval)

| # | Point | Reading used |
|---|---|---|
| R1 | Largest amount | 1 to 999 999 999 999 minor units (999 999 999.999 TND). This fits a JavaScript safe integer, so the app never loses precision. |
| R2 | Currency in answers | Every amount is sent as `{"amount": 125500, "currency": "TND"}`. The book's currency, never chosen per entry. |
| R3 | Retired categories | A new entry or rule may not use a retired leaf (`category.retired`). An existing entry keeps its retired leaf, and may be edited without changing it. A rule whose leaf is retired later keeps running. |
| R4 | Changing category | On an edit, the E5 rule applies to the result: moving to an "Other" leaf requires `other_name`; moving away from one clears it. Moving to a leaf of another type (expense to income) is allowed. |
| R5 | Who is the author of a generated entry | The account that created the rule. The entry says it came from a rule (`recurring_rule_id`). |
| R6 | A member leaves or is removed | Their entries and rules stay in the household's book (the book owns the data, D6). Their rules keep running. Their name is still shown on what they recorded. They lose access at once, read at each request. |
| R7 | A deleted entry made by a rule | It is never recreated. A rule makes at most one entry per due date, also when two jobs run at once. |
| R8 | Editing a rule's schedule | The new schedule applies from today: no entry dated before today is created, changed or removed. Today's occurrence is created if the new schedule has one and it does not exist yet. |
| R9 | Rule dates | `starts_on` from today − 1 year to today + 1 year (book timezone). `ends_on` optional, on or after `starts_on`. A rule past its `ends_on` stays listed but makes no more entries. |
| R10 | How often the job runs | Every hour, so each book's "today" is reached soon after its own midnight. Running it twice changes nothing. |
| R11 | Suggestions | `GET /api/v1/books/{book}/other-names?category=<code of an Other leaf>` returns up to 10 names typed for that leaf in that book (entries and rules, deleted entries left out), most used first, then most recent. Names that differ only in letter case count as one. |
| R12 | Client id clashes | An `id` already used by an entry of another book, or by a deleted entry, answers `409 transaction.id_conflict`. A repeated `id` returns the stored entry as it is, even if the new body differs. |
| R13 | Rate limit | 60 writes per minute per account, entries and rules together (create, edit, delete). Reads use the default API limit. |
| R14 | Rule limit | At most 100 live rules per book. |
| R15 | Page size and order | 25 entries per page, cursor pagination. Ordered by date (newest first), then by creation time (newest first). |
| R16 | Editable entry fields | `amount`, `date`, `category`, `other_name`, `comment`. The book, the author and `recurring_rule_id` never change. The account of the last edit (`updated_by`) is stored and shown. |

## User stories

- **US-1** — As a member of a book, I want to record an expense, an income or a saving in a few taps, so that our book stays up to date.
- **US-2** — As a member of a book, I want to see the entries, newest first, and filter them by period, category, type and person, so that I find what I am looking for.
- **US-3** — As a member of a book, I want to correct or delete any entry, so that mistakes don't stay in our numbers.
- **US-4** — As a member of a book, I want regular amounts (rent, salary, subscriptions) recorded for me on their date, so that I don't type them every month.
- **US-5** — As someone picking "Other", I want the names we already typed suggested, so that the same thing gets the same name.
- **US-6** — As a mobile user on a weak network, I want a retried save never to create a duplicate.
- **US-7** — As the owner of the app, I want no one to reach a book they don't belong to, and money stored exactly.

## Acceptance criteria (EARS)

### Shared rules

- **REQ-0.1** Every route of this spec SHALL be nested under `/api/v1/books/{book}` and guarded by `RequireBook`. IF the caller is not signed in THEN THE SYSTEM SHALL answer `401 auth.unauthenticated`. IF the book does not exist, is deleted, or the caller has no access THEN THE SYSTEM SHALL answer `404 book.not_found`, the same in every case (D8).
- **REQ-0.2** Access SHALL be: a personal book, its account only; a household's book, every live member of the household, in any role (E2). Membership SHALL be read at each request.
- **REQ-0.3** IF an entry or rule id in the path does not exist, is deleted, or belongs to another book THEN THE SYSTEM SHALL answer `404 transaction.not_found` / `404 recurring_rule.not_found`, the same in every case.
- **REQ-0.4** Field rules for entries and rules, each failure giving `422 validation.failed` with the field code:
  - `amount`: required on create; an integer from 1 to 999 999 999 999 (R1). Codes `amount.required`, `amount.integer`, `amount.min`, `amount.max`.
  - `category`: required on create; the code of an existing leaf (`category.not_found`, `category.not_leaf`), not retired on create or when changed (`category.retired`, R3).
  - `other_name`: required on an "Other" leaf (`other_name.required`), refused on any other leaf (`other_name.prohibited`), trimmed, 1–60 characters (`other_name.max`) (E5).
  - `comment`: optional, trimmed, up to 200 characters (`comment.max`); empty becomes `null` (E6).
  - Any other field SHALL be ignored, and `book_id`, author fields and `recurring_rule_id` SHALL never be taken from the request.
- **REQ-0.5** Every amount in an answer SHALL be an integer of minor units together with the book's currency code (R2). THE SYSTEM SHALL never use floats or decimals for amounts.
- **REQ-0.6** Writes (create, edit, delete of entries and rules) SHALL be limited to 60 per minute per account, together (R13). Over the limit THE SYSTEM SHALL answer `429 request.throttled`.
- **REQ-0.7** Every endpoint SHALL behave the same in cookie mode and token mode.

### US-1, US-6 — Record an entry

- **REQ-1.1** WHEN a member posts a valid entry to `POST /api/v1/books/{book}/transactions` THE SYSTEM SHALL store it with the book, the caller as author, and answer `201` with the entry: `id`, `amount`, `currency`, `date`, `type` (from the category), `category` (code), `other_name`, `comment`, `recurring_rule_id`, `created_by` and `updated_by` (each `{id, name}`), `created_at`, `updated_at`.
- **REQ-1.2** `date` SHALL be required, a calendar date `YYYY-MM-DD`, from `2000-01-01` to today + 1 year in the book's timezone (E4). Codes `date.required`, `date.date_format`, `date.after_or_equal`, `date.before_or_equal`.
- **REQ-1.3** WHEN the request carries an `id` (a UUID, `id.uuid`) that no entry uses THE SYSTEM SHALL store the entry under that id. WHEN that id is already an entry of the same book THE SYSTEM SHALL answer `200` with that entry and store nothing (E9). IF that id is used by an entry of another book, or by a deleted entry, THEN THE SYSTEM SHALL answer `409 transaction.id_conflict` (R12). Two requests with the same id at the same time SHALL store one entry.
- **REQ-1.4** Without an `id`, THE SYSTEM SHALL generate one.

### US-2 — List and read entries

- **REQ-2.1** WHEN a member calls `GET /api/v1/books/{book}/transactions` THE SYSTEM SHALL answer `200` with the book's live entries, 25 per page with cursor pagination, newest date first, then newest created first (R15).
- **REQ-2.2** The list SHALL accept these filters, combined with AND (E7):
  - `from` and `to`: `YYYY-MM-DD`, inclusive; `to` before `from` gives `to.after_or_equal`;
  - `category`: the code of a top, group or leaf category, matching every entry whose leaf is that category or under it; an unknown code gives `category.not_found`;
  - `type`: `expense`, `income` or `saving` (`type.in`);
  - `author`: an account id (`author.uuid`); an id with no entries gives an empty list, not an error.
- **REQ-2.3** WHEN a member calls `GET /api/v1/books/{book}/transactions/{transaction}` THE SYSTEM SHALL answer `200` with the entry (REQ-1.1 shape).
- **REQ-2.4** A deleted entry SHALL appear in no list and answer `404 transaction.not_found`.
- **REQ-2.5** The author's and last editor's names SHALL be read through `Identity`'s `FindAccountNames`, also for accounts that have left the household (R6). A list page SHALL read them in one call, not one per entry.

### US-3 — Edit and delete an entry

- **REQ-3.1** WHEN a member sends `PATCH /api/v1/books/{book}/transactions/{transaction}` with any of `amount`, `date`, `category`, `other_name`, `comment` THE SYSTEM SHALL change only those fields, set the caller as `updated_by`, and answer `200` with the entry (R16). The rules of REQ-0.4 and REQ-1.2 apply to each field sent.
- **REQ-3.2** The "Other" rule SHALL apply to the result (R4): a change to an "Other" leaf without `other_name` gives `other_name.required`; a change to another leaf clears `other_name`.
- **REQ-3.3** An entry whose leaf is retired SHALL be editable without changing its category (R3).
- **REQ-3.4** WHEN a member calls `DELETE /api/v1/books/{book}/transactions/{transaction}` THE SYSTEM SHALL soft-delete it and answer `204`.
- **REQ-3.5** Any live member of a household's book SHALL be able to edit and delete any entry of it, whoever recorded it (E2).
- **REQ-3.6** Editing or deleting an entry made by a rule SHALL change neither the rule nor its other entries (E13).

### US-4 — Recurring rules

- **REQ-4.1** WHEN a member posts a valid rule to `POST /api/v1/books/{book}/recurring-rules` THE SYSTEM SHALL store it with the caller as author and answer `201` with the rule: `id`, `amount`, `currency`, `type`, `category`, `other_name`, `comment`, `frequency`, `interval`, `starts_on`, `ends_on`, `next_date` (the next date an entry will be made, `null` when none), `created_by`, `updated_by`, `created_at`, `updated_at`.
- **REQ-4.2** Rule fields, besides REQ-0.4:
  - `frequency`: required, `weekly`, `monthly` or `yearly` (`frequency.in`) (E10);
  - `interval`: optional, default 1, an integer from 1 to 12 (`interval.min`, `interval.max`);
  - `starts_on`: required, from today − 1 year to today + 1 year in the book's timezone (R9);
  - `ends_on`: optional, on or after `starts_on` (`ends_on.after_or_equal`).
- **REQ-4.3** The due dates of a rule SHALL be `starts_on`, then every `interval` weeks, months or years after it. WHEN the day of `starts_on` does not exist in a month THE SYSTEM SHALL use that month's last day, and return to the original day in the next month that has it (31 January → 28 or 29 February → 31 March) (E10). No due date SHALL fall after `ends_on`.
- **REQ-4.4** WHEN a rule is created with `starts_on` on or before today THE SYSTEM SHALL create, in the same transaction, one entry for every due date from `starts_on` up to today (E12).
- **REQ-4.5** THE SYSTEM SHALL run a scheduled job every hour (R10). For every live rule of every live book, it SHALL create one entry for each due date up to today in the book's timezone that has no entry yet, catching up any missed days (E11). Each entry copies the rule's amount, category, `other_name` and `comment`, has the rule's author as `created_by` (R5) and the rule's id as `recurring_rule_id`.
- **REQ-4.6** A rule SHALL make at most one entry per due date, ever: a deleted entry is not recreated, and two jobs running at once create one entry (R7).
- **REQ-4.7** IF one rule fails in the job THEN THE SYSTEM SHALL log it (ids only), go on with the other rules, and try it again at the next run.
- **REQ-4.8** WHEN a member calls `GET /api/v1/books/{book}/recurring-rules` THE SYSTEM SHALL answer `200` with the book's live rules, cursor-paginated, ordered by `next_date` (rules with none last). `GET …/recurring-rules/{rule}` SHALL answer one rule.
- **REQ-4.9** WHEN a member sends `PATCH /api/v1/books/{book}/recurring-rules/{rule}` with any field of REQ-4.2 or REQ-0.4 THE SYSTEM SHALL change the rule and answer `200`. Entries already created SHALL not change (E13). The new schedule applies from today (R8): no entry dated before today is created; today's is created if due and missing.
- **REQ-4.10** WHEN a member calls `DELETE /api/v1/books/{book}/recurring-rules/{rule}` THE SYSTEM SHALL soft-delete it, answer `204`, and make no more entries from it. Its entries stay, with their `recurring_rule_id`.
- **REQ-4.11** IF a book already has 100 live rules THEN creating one SHALL answer `422 recurring_rule.limit_reached` (R14).
- **REQ-4.12** WHILE a book is deleted (a household deleted, spec 003) THE SYSTEM SHALL make no entries for its rules.

### US-5 — "Other" suggestions

- **REQ-5.1** WHEN a member calls `GET /api/v1/books/{book}/other-names?category=<code>` with the code of an "Other" leaf THE SYSTEM SHALL answer `200` with up to 10 names typed for that leaf in that book, in live entries and live rules, most used first, then most recently used (R11).
- **REQ-5.2** Names that differ only in letter case SHALL count as one, shown as most recently typed.
- **REQ-5.3** IF `category` is missing, unknown or not an "Other" leaf THEN THE SYSTEM SHALL answer `422` with `category.required`, `category.not_found` or `category.not_other`.

### US-7 — Data, access, modules

- **REQ-6.1** `transactions` and `recurring_rules` SHALL each have a `book_id`, and every query SHALL be scoped to the current book.
- **REQ-6.2** Amounts SHALL be stored as `bigint` minor units. Dates SHALL be stored as `date`; timestamps in UTC.
- **REQ-6.3** Entries and rules SHALL be soft-deleted, never removed. No foreign key from them to categories, books or users SHALL cascade on delete.
- **REQ-6.4** Each endpoint SHALL have feature tests for success, validation errors, `401` in both modes, and a member of another household and the account of another personal book getting `404`. Every rule of this spec SHALL have a test named after its `REQ` id.
- **REQ-6.5** The due-date rule (REQ-4.3) SHALL have unit tests for each frequency, intervals above 1, month ends, 29 February, `ends_on`, and a book timezone ahead of and behind UTC around midnight.
- **REQ-6.6** No log line SHALL hold an amount, an "Other" name, a comment or a name.
- **REQ-6.7** The architecture tests SHALL still pass: `Ledger` uses only `Household`, `Identity` and `Shared`; nothing uses `Ledger`.

### Running and documentation

- **REQ-7.1** `docker-compose.yml` SHALL run Laravel's scheduler, so the job of REQ-4.5 runs in local development. The README's deploy steps SHALL say the scheduler must run in production (`schedule:run` every minute, or `schedule:work`).
- **REQ-7.2** `specs/006-transactions/contract.md` and the Postman collection SHALL describe every route of this spec with its error codes.
- **REQ-7.3** `CLAUDE.md` SHALL be updated: "Current state" lists the transactions, rules and suggestions endpoints and the scheduler, and spec 007 (summary) comes next; the Docker section lists the scheduler service.
- **REQ-7.4** The README SHALL describe the new endpoints and the scheduler.

Checklist — have these been covered?
- [x] Validation errors (per field, with stable codes) — REQ-0.4, 1.2, 2.2, 4.2, 5.3
- [x] Unauthenticated access (401), cookie and token modes — REQ-0.1, 0.7
- [x] Access to another household's data — REQ-0.1–0.3, 6.4
- [x] Rate limits / repeated requests / duplicates — REQ-0.6, 1.3, 4.6, 4.11
- [x] Money edge cases (zero, negative, currency exponent) — REQ-0.4, 0.5 (R1, R2)
- [x] Date & timezone edge cases (household timezone, month boundaries) — REQ-1.2, 4.2–4.5, 6.5
- [x] Deleting / changing referenced data — REQ-2.4, 3.3, 3.6, 4.10, 4.12, 6.3 (R3, R6)

## Non-goals

- Totals, the monthly summary, and grouping "Other" entries in it (spec 007). The list sends no totals.
- Daily rules, "pending" entries to confirm, and entries created ahead of their date.
- Editing past entries through their rule ("this and all following"), and pausing a rule.
- Restoring a deleted entry or rule.
- Transfers between books, several currencies in one book, and attachments (receipt photos).
- Searching comments or "Other" names as text.
- Private entries (D6).
- The offline queue on mobile (the client id of E9 prepares it).
- The frontend.

## Open questions

None left from the dialog. Please confirm the readings R1–R16 at approval.
