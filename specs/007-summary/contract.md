# 007 — Summary: API contract

> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).
> Auth: a signed-in user, by session cookie (web) or `Authorization: Bearer <token>` (mobile), see [spec 002](../002-accounts/contract.md#modes). Without one: `401 auth.unauthenticated`.
> Access, amounts and dates: as in [spec 006](../006-transactions/contract.md#shared-rules). Any member of a household may read its book's summary.

## GET books/{book}/summary

Where the money of a period went: totals per type, the balance, and the same totals by category, by member, by day and by month. Computed on each request from the book's live entries dated in the period. Deleted entries never count, and recurring rules count only for the entries they have already made.

### Query

Send one of the following:

| Choice | Parameters | Example |
|---|---|---|
| This month | none (the current month in the book's timezone) | `/summary` |
| One month | `month=YYYY-MM` | `/summary?month=2026-11` |
| Custom range | `from=YYYY-MM-DD&to=YYYY-MM-DD`, both days included, at most one year | `/summary?from=2026-10-25&to=2026-11-24` |

"At most one year" means `to` is before `from` + 1 year: `2026-01-01` → `2026-12-31` is allowed, `2026-01-01` → `2027-01-01` is not.

### Response `200`

```json
{
  "data": {
    "from": "2026-11-01",
    "to": "2026-11-30",
    "currency": "TND",
    "totals": {"income": 2500000, "expense": 912500, "saving": 300000, "balance": 1287500, "entries": 4},
    "categories": [
      {
        "category": "food",
        "type": "expense",
        "total": 62500,
        "entries": 2,
        "children": [
          {
            "category": "food.groceries",
            "type": "expense",
            "total": 62500,
            "entries": 2,
            "children": [
              {
                "category": "food.groceries.other",
                "type": "expense",
                "total": 62500,
                "entries": 2,
                "children": [],
                "other_names": [
                  {"name": "Marché", "total": 50000, "entries": 1},
                  {"name": "Épicerie du coin", "total": 12500, "entries": 1}
                ]
              }
            ],
            "other_names": []
          }
        ],
        "other_names": []
      }
    ],
    "members": [
      {
        "account": {"id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b", "name": "Sofiane"},
        "income": 2500000, "expense": 912500, "saving": 300000, "balance": 1287500, "entries": 4
      }
    ],
    "days": [
      {"date": "2026-11-01", "income": 2500000, "expense": 850000, "saving": 0, "balance": 1650000, "entries": 2}
    ],
    "months": [
      {"month": "2026-11", "income": 2500000, "expense": 912500, "saving": 300000, "balance": 1287500, "entries": 4}
    ]
  }
}
```

The example is shortened: `totals` also counts entries not shown in the lists.

- **Amounts** are integers in the minor unit of `currency` (TND: millimes; see the book's `currency.exponent`). `balance` = `income` − `expense` − `saving`, and may be negative. `entries` is a number of entries.
- **`from`, `to`**: the period covered, `YYYY-MM-DD`.
- **`categories`**: only categories with entries in the period. Each level is a list of tops, groups or leaves in the order of the category tree (`GET /categories`). A node's `total` and `entries` add up everything under it. Every node has `children` (empty on a leaf) and `other_names`. Retired categories appear like the others, by their `code`.
- **`other_names`**: filled only on an "Other" leaf. Names that differ only in letter case count as one, shown as most recently typed. Accents count: "café" and "cafe" are two names. Ordered by `total`, largest first, then by name.
- **`members`**: one line per account that recorded entries in the period. Entries made by a recurring rule count for the rule's author. A person who has left the household keeps their line and name. Ordered by `expense`, largest first.
- **`days`**, **`months`**: only days and months with entries, oldest first. A month line covers only its days inside the period.
- With no entries in the period, every total is `0` and every list is empty.

### Errors

| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | — |
| 404 | `book.not_found` | the book does not exist, is deleted, or you may not use it |
| 422 | `validation.failed` | see field codes |

Field codes:

| Field | Codes |
|---|---|
| `month` | `month.date_format` (`YYYY-MM`), `month.after_or_equal` (2000-01), `month.prohibits` (sent with `from` or `to`) |
| `from` | `from.required_with` (`to` sent alone), `from.date_format` (`YYYY-MM-DD`, a real day), `from.after_or_equal` (2000-01-01) |
| `to` | `to.required_with` (`from` sent alone), `to.date_format`, `to.after_or_equal` (on or after `from`), `to.within_one_year` |
