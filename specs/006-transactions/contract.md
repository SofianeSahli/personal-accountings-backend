# 006 — Transactions and recurring rules: API contract

> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).
> Auth: every route below needs a signed-in user, by session cookie (web) or `Authorization: Bearer <token>` (mobile), see [spec 002](../002-accounts/contract.md#modes). Without one: `401 auth.unauthenticated`.

## Shared rules

### Access

Every route is nested under a book: `/books/{book}/…`. You may use a book when it is your personal book, or the book of the household you belong to (any role: `owner` or `member`). Any member may record, edit and delete any entry and any rule of the household's book.

A book that does not exist, a malformed id, a deleted book and a book you cannot use all answer:

```json
{"error": {"code": "book.not_found"}}
```

An entry or rule id that does not exist, is malformed, is deleted or belongs to another book answers `404 transaction.not_found` or `404 recurring_rule.not_found`.

Order of checks: `401` → `404 book` → `404 entry or rule` → `422` field codes → `409` (repeated `id`) → `422` category codes.

### Throttling

Writes (`POST`, `PATCH`, `DELETE` of entries and rules) share one limit: **60 per minute per account**. Over it: `429 request.throttled` with `Retry-After`. Reads use no extra limit.

### Amounts and dates

- `amount` is always a positive integer in the minor unit of the book's currency (TND: millimes, 3 decimals; EUR: cents), from `1` to `999999999999`. Every answer also sends `currency`, the book's ISO 4217 code. The book's `currency.exponent` (`GET books/{book}`) says how many decimals to show.
- The direction of the money comes from the category's `type`: `expense`, `income` or `saving`. Amounts are never negative.
- Dates are `YYYY-MM-DD`, without time. "Today" is the date in the book's timezone.

### Shapes

#### Entry

```json
{
  "id": "0193b2a0-1c2d-7e3f-8a4b-5c6d7e8f9a0b",
  "amount": 12500,
  "currency": "TND",
  "date": "2026-11-20",
  "type": "expense",
  "category": "food.groceries.other",
  "other_name": "Épicerie du coin",
  "comment": "Courses de la semaine",
  "recurring_rule_id": null,
  "created_by": {"id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b", "name": "Sofiane"},
  "updated_by": {"id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b", "name": "Sofiane"},
  "created_at": "2026-11-20T18:04:11Z",
  "updated_at": "2026-11-20T18:04:11Z"
}
```

- `category` is the leaf's code (see [spec 005](../005-categories/contract.md)); `type` is its type.
- `other_name` is set only on an "Other" leaf (`is_other: true` in the category tree), else `null`.
- `recurring_rule_id` is set when a recurring rule made the entry.
- `created_by` / `updated_by`: the account that recorded the entry and the one that last edited it. For an entry made by a rule, both are the rule's author. Names stay shown after the person leaves the household.

#### Recurring rule

```json
{
  "id": "0193b2a1-5e6f-7a8b-9c0d-1e2f3a4b5c6d",
  "amount": 850000,
  "currency": "TND",
  "type": "expense",
  "category": "home.housing.rent",
  "other_name": null,
  "comment": "Loyer",
  "frequency": "monthly",
  "interval": 1,
  "starts_on": "2026-01-31",
  "ends_on": null,
  "next_date": "2026-11-30",
  "created_by": {"id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b", "name": "Sofiane"},
  "updated_by": {"id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b", "name": "Sofiane"},
  "created_at": "2026-11-20T18:04:11Z",
  "updated_at": "2026-11-20T18:04:11Z"
}
```

- Due dates are `starts_on`, then every `interval` weeks, months or years after it, never after `ends_on`. A day the month lacks falls on the month's last day (31 January → 28/29 February → 31 March; 29 February → 28 February in common years).
- `next_date` is the next date an entry will be made, `null` once the rule has ended.
- On each due date, in the book's timezone, the server records the entry by itself (an hourly job, which catches up missed days). An entry never exists before its date.

#### Lists (cursor pagination)

As in [spec 003](../003-households/contract.md#lists-cursor-pagination): 25 items per page, `?cursor=<meta.next_cursor>`.

### Field codes

`422 validation.failed` with `fields`. Strings are trimmed; an empty string counts as missing.

| Field | Codes |
|---|---|
| `amount` | `amount.required`, `amount.integer` (a JSON integer: `"125"` and `12.5` are refused), `amount.min` (1), `amount.max` (999999999999) |
| `date` | `date.required`, `date.date_format` (`YYYY-MM-DD`, a real day), `date.after_or_equal` (2000-01-01), `date.before_or_equal` (today + 1 year) |
| `category` | `category.required`, `category.string`, `category.not_found`, `category.not_leaf` (a top or a group), `category.retired` (a retired leaf, when it is new to the entry or rule) |
| `other_name` | `other_name.required` (on an "Other" leaf), `other_name.prohibited` (on any other leaf), `other_name.string`, `other_name.max` (60) |
| `comment` | `comment.string`, `comment.max` (200) |
| `id` | `id.uuid` |
| `frequency` | `frequency.required`, `frequency.in` (`weekly`, `monthly`, `yearly`) |
| `interval` | `interval.required` (when sent empty on edit), `interval.integer`, `interval.min` (1), `interval.max` (12) |
| `starts_on` | `starts_on.required`, `starts_on.date_format`, `starts_on.after_or_equal` (today − 1 year), `starts_on.before_or_equal` (today + 1 year) |
| `ends_on` | `ends_on.date_format`, `ends_on.after_or_equal` (on or after `starts_on`) |

Field checks run first; the category and "Other" checks run once the fields are well formed, so an answer may hold the field codes only.

---

## GET books/{book}/transactions

The book's live entries, newest `date` first, then newest recorded first.

Query (all optional, combined with AND)

| Parameter | Meaning | Codes |
|---|---|---|
| `from`, `to` | inclusive date range, `YYYY-MM-DD` | `from.date_format`, `to.date_format`, `to.after_or_equal` |
| `category` | a top, group or leaf code: entries of that leaf or any leaf under it | `category.not_found` |
| `type` | `expense`, `income` or `saving` | `type.in` |
| `author` | an account id: entries recorded by that account (no match: empty list) | `author.uuid` |
| `cursor` | from `meta.next_cursor` | — |

Response `200`: list of Entry. No totals (spec 007).

Errors: `401`, `404 book.not_found`, `422 validation.failed`.

## POST books/{book}/transactions

Records an entry. You are its author.

Throttle: ledger writes.

Request

```json
{
  "id": "0193b2a0-1c2d-7e3f-8a4b-5c6d7e8f9a0b",
  "amount": 12500,
  "date": "2026-11-20",
  "category": "food.groceries.other",
  "other_name": "Épicerie du coin",
  "comment": "Courses de la semaine"
}
```

- `id` (optional): a UUID made by the client. Send the same `id` again to the same book and the answer is `200` with the stored entry, unchanged, even if the new body differs. Use it to retry safely.
- `book_id`, author fields and `recurring_rule_id` are never read from the body.

Response `201` (or `200` for a repeated `id`): `{"data": Entry}`

Errors

| Status | Code | When |
|---|---|---|
| 404 | `book.not_found` | — |
| 409 | `transaction.id_conflict` | `id` is used by an entry of another book, or by a deleted entry |
| 422 | `validation.failed` | see field codes |
| 429 | `request.throttled` | — |

## GET books/{book}/transactions/{transaction}

Response `200`: `{"data": Entry}`

Errors: `401`, `404 book.not_found`, `404 transaction.not_found`.

## PATCH books/{book}/transactions/{transaction}

Changes only the fields sent: `amount`, `date`, `category`, `other_name`, `comment`. The book, the author and `recurring_rule_id` never change. You become `updated_by`.

Throttle: ledger writes.

- Moving to an "Other" leaf needs `other_name`; moving to another leaf clears it. On an unchanged "Other" leaf, the stored name is kept unless you send a new one.
- An entry whose leaf has been retired can still be edited, as long as `category` stays the same.
- `comment: null` (or `""`) clears the comment.
- Editing an entry made by a rule changes neither the rule nor its other entries.

Request

```json
{"amount": 13250, "comment": null}
```

Response `200`: `{"data": Entry}`

Errors: `401`, `404 book.not_found`, `404 transaction.not_found`, `422 validation.failed`, `429 request.throttled`.

## DELETE books/{book}/transactions/{transaction}

Deletes the entry. It is kept in the database (soft-deleted), answers `404` afterwards and leaves every list. An entry made by a rule is never made again.

Throttle: ledger writes.

Response `204`, no body.

Errors: `401`, `404 book.not_found`, `404 transaction.not_found`, `429 request.throttled`.

---

## GET books/{book}/recurring-rules

The book's live rules, by `next_date` (soonest first), rules with no next date last.

Response `200`: list of Recurring rule.

Errors: `401`, `404 book.not_found`.

## POST books/{book}/recurring-rules

Creates a rule. You are its author, and the author of every entry it makes.

Throttle: ledger writes.

Request

```json
{
  "amount": 850000,
  "category": "home.housing.rent",
  "comment": "Loyer",
  "frequency": "monthly",
  "interval": 1,
  "starts_on": "2026-01-31",
  "ends_on": null
}
```

- `interval` defaults to `1`. `other_name` and `comment` follow the entry rules.
- A `starts_on` today or in the past creates, at once, one entry per due date up to today (at most one year back, so at most 53 weekly entries).

Response `201`: `{"data": Recurring rule}`

Errors

| Status | Code | When |
|---|---|---|
| 404 | `book.not_found` | — |
| 422 | `recurring_rule.limit_reached` | the book already has 100 live rules |
| 422 | `validation.failed` | see field codes |
| 429 | `request.throttled` | — |

## GET books/{book}/recurring-rules/{rule}

Response `200`: `{"data": Recurring rule}`

Errors: `401`, `404 book.not_found`, `404 recurring_rule.not_found`.

## PATCH books/{book}/recurring-rules/{rule}

Changes only the fields sent: `amount`, `category`, `other_name`, `comment`, `frequency`, `interval`, `starts_on`, `ends_on` (`null` removes the end).

Throttle: ledger writes.

- Entries already made never change. Anything due up to today is first made with the old values.
- A new schedule (`frequency`, `interval`, `starts_on`, `ends_on`) applies from today: no entry dated before today is created, and today's entry is made if the new schedule has one.
- `ends_on` must be on or after the resulting `starts_on` (`ends_on.after_or_equal`).

Request

```json
{"amount": 900000, "ends_on": "2027-06-30"}
```

Response `200`: `{"data": Recurring rule}`

Errors: `401`, `404 book.not_found`, `404 recurring_rule.not_found`, `422 validation.failed`, `429 request.throttled`.

## DELETE books/{book}/recurring-rules/{rule}

Stops the rule. It is kept in the database (soft-deleted) and answers `404` afterwards. The entries it made stay, with their `recurring_rule_id`.

Throttle: ledger writes.

Response `204`, no body.

Errors: `401`, `404 book.not_found`, `404 recurring_rule.not_found`, `429 request.throttled`.

---

## GET books/{book}/other-names?category={code}

Names already typed in this book for an "Other" leaf, in live entries and live rules: up to 10, most used first, then most recently used. Names that differ only in letter case count as one, shown as most recently typed (accents count: "café" and "cafe" are two names).

Response `200`

```json
{"data": ["Épicerie du coin", "Marché"]}
```

Errors

| Status | Code | When |
|---|---|---|
| 404 | `book.not_found` | — |
| 422 | `validation.failed` | `category.required`, `category.not_found`, `category.not_other` (not an "Other" leaf) |
