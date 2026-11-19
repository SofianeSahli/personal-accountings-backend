# 003 — Books and households: API contract

> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).
> Auth: every route below needs a signed-in user, by session cookie (web) or `Authorization: Bearer <token>` (mobile), see [spec 002](../002-accounts/contract.md#modes). Without one: `401 auth.unauthenticated`.

## Shared shapes

### Household

```json
{"id": "0193b0c1-2d3e-7f40-8a1b-2c3d4e5f6a7b", "name": "Foyer Sahli", "role": "owner"}
```

`role` is the caller's role: `owner` or `member`.

### Member

```json
{"id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b", "name": "Sofiane", "role": "owner"}
```

`id` is the account id. No other account field is ever returned.

### Book

```json
{
  "id": "0193b0c2-9a8b-7c6d-8e5f-4a3b2c1d0e9f",
  "name": "Dépenses du foyer",
  "currency": {"code": "TND", "exponent": 3},
  "timezone": "Africa/Tunis",
  "owner": {"type": "household", "id": "0193b0c1-2d3e-7f40-8a1b-2c3d4e5f6a7b"},
  "can_manage": true
}
```

- `owner.type` is `account` (a personal book, `id` is the account id) or `household`.
- `can_manage`: the caller may rename the book and change its timezone. Always `true` on a personal book; on a household's book, `true` only for the household's owner.
- `currency.exponent`: number of decimals of the minor unit (TND 3, EUR 2, JPY 0). Amounts in later specs are integers in that unit.

### Lists (cursor pagination)

Every list in the API has this shape. 25 items per page. Pass `?cursor=<next_cursor>` for the next page; `next_cursor` is `null` on the last page. Cursors are opaque.

```json
{
  "data": [ { "…": "item" } ],
  "links": {"first": null, "last": null, "prev": null, "next": "https://api.example.com/api/v1/books?cursor=eyJ…"},
  "meta": {"path": "https://api.example.com/api/v1/books", "per_page": 25, "next_cursor": "eyJ…", "prev_cursor": null}
}
```

### Field codes

Validation errors are `422 validation.failed` with `fields`. Strings are trimmed; an empty string counts as missing.

| Field | Codes |
|---|---|
| `name` | `name.required`, `name.string`, `name.max` (60) |
| `currency` (create) | `currency.string`, `currency.supported_currency` (unknown ISO 4217 code; any letter case accepted) |
| `currency` (update) | `currency.prohibited` |
| `timezone` | `timezone.required` (update, when sent empty), `timezone.string`, `timezone.iana_timezone` (not a canonical IANA name; case-sensitive) |
| `household_id` (create book) | `household_id.string`, `household_id.not_found` (unknown, malformed, or not your household) |
| `household_id` (update book) | `household_id.prohibited` |

### Not found and forbidden

A household or book that does not exist, a malformed id and one you have no access to all give the same answer:

```json
{"error": {"code": "book.not_found"}}
```

Order of checks on routes with an id in the path: `401` → `404` → `403` → `422`.

### Throttling

`POST households` and `POST books` share one limit: 10 per minute per account.

---

## GET households

Your household, or an empty list. A user belongs to at most one household.

Response `200`: list of Household.

## POST households

Creates a household with you as `owner`. It has no book yet: create it with `POST books` and `household_id`.

Throttle: creations.

Request
```json
{"name": "Foyer Sahli"}
```

Response `201`: `{"data": Household}`

Errors
| Status | Code | When |
|---|---|---|
| 422 | `household.limit_reached` | you already belong to a household (as owner or member) |
| 422 | `validation.failed` | see field codes |
| 429 | `request.throttled` | — |

## GET households/{household}

Response `200`: `{"data": Household}`

Errors: `404 household.not_found`.

## PATCH households/{household}

Owner only. Renames the household. An empty body changes nothing and answers `200`.

Request
```json
{"name": "Famille Sahli"}
```

Response `200`: `{"data": Household}`

Errors
| Status | Code | When |
|---|---|---|
| 404 | `household.not_found` | — |
| 403 | `household.forbidden` | you are a `member` |
| 422 | `validation.failed` | see field codes |

## DELETE households/{household}

Owner only. Deletes the household: every member leaves it at once and is free to create or join another household, and the household's book is deleted with it. Nothing is erased: the household, its memberships and its book are kept in the database, soft-deleted, and are no longer reachable through the API.

Response `204`, no body. Afterwards the household and its book answer `404` and are left out of `GET households` and `GET books`.

Errors
| Status | Code | When |
|---|---|---|
| 404 | `household.not_found` | also for a household already deleted |
| 403 | `household.forbidden` | you are a `member` |

## GET households/{household}/members

Owners first, then by date joined.

Response `200`: list of Member.

Errors: `404 household.not_found`.

---

## GET books

Your personal books and your household's book, oldest first.

Response `200`: list of Book.

## POST books

Creates a personal book, or your household's book when `household_id` is sent.

Throttle: creations.

Request, personal book
```json
{"name": "Mes dépenses", "currency": "eur", "timezone": "Europe/Paris"}
```

Request, household's book
```json
{"name": "Dépenses du foyer", "household_id": "0193b0c1-2d3e-7f40-8a1b-2c3d4e5f6a7b"}
```

`currency` defaults to `TND` and is stored in upper case; `timezone` defaults to `Africa/Tunis`. The currency can never be changed later.

Response `201`: `{"data": Book}`

Errors (in this order)
| Status | Code | When |
|---|---|---|
| 429 | `request.throttled` | — |
| 422 | `validation.failed` | see field codes, including `household_id.not_found` |
| 403 | `household.forbidden` | you are a `member` of that household, not its owner |
| 422 | `book.household_has_book` | the household already has its book |
| 422 | `book.limit_reached` | you already have 5 personal books (the household's book is not counted) |

## GET books/{book}

Response `200`: `{"data": Book}`

Errors: `404 book.not_found`.

## PATCH books/{book}

Renames the book and/or changes its timezone. Fields left out are unchanged; an empty body changes nothing and answers `200`. Changing the timezone changes no stored data.

Request
```json
{"name": "Courses", "timezone": "Europe/Paris"}
```

Response `200`: `{"data": Book}`

Errors
| Status | Code | When |
|---|---|---|
| 404 | `book.not_found` | — |
| 403 | `book.forbidden` | `can_manage` is `false` (a member of the household) |
| 422 | `validation.failed` | see field codes; `currency` and `household_id` are refused with `.prohibited` |
