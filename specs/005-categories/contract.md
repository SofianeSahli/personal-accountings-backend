# 005 — Categories: API contract

> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).
> Auth: a signed-in user, by session cookie (web) or `Authorization: Bearer <token>` (mobile), see [spec 002](../002-accounts/contract.md#modes). Without one: `401 auth.unauthenticated`.

## GET /categories

The whole category tree: top categories → groups → leaves, each level in display order. The same for every account. No names: the app translates `translation_key` from its own files (`fr.json`, `en.json`, `ar.json`).

Auth: cookie session or bearer token · Policy: none (global list) · Throttle: 60 a minute per account.

Request headers (optional)

```
If-None-Match: "6f1c2a…"
```

Response `200`

Headers: `ETag: "6f1c2a…"`, `Cache-Control: no-cache, private`.

```json
{
  "data": [
    {
      "code": "food",
      "translation_key": "categories.food.label",
      "emoji": "🛒",
      "type": "expense",
      "retired": false,
      "groups": [
        {
          "code": "food.groceries",
          "translation_key": "categories.food.groceries.label",
          "emoji": "🏪",
          "retired": false,
          "leaves": [
            {
              "code": "food.groceries.supermarket",
              "translation_key": "categories.food.groceries.supermarket.label",
              "emoji": "🥫",
              "retired": false,
              "is_other": false
            },
            {
              "code": "food.groceries.other",
              "translation_key": "categories.food.groceries.other.label",
              "emoji": "📝",
              "retired": false,
              "is_other": true
            }
          ]
        }
      ]
    },
    {
      "code": "celebrations",
      "translation_key": "categories.celebrations.label",
      "emoji": "🎉",
      "type": "expense",
      "retired": false,
      "groups": [
        {
          "code": "celebrations.muslim_holidays",
          "translation_key": "categories.celebrations.muslim_holidays.label",
          "emoji": "🌙",
          "retired": false,
          "leaves": [
            {
              "code": "celebrations.muslim_holidays.ramadan",
              "translation_key": "categories.celebrations.muslim_holidays.ramadan.label",
              "emoji": null,
              "retired": false,
              "is_other": false
            }
          ]
        }
      ]
    }
  ]
}
```

(Shortened: the real answer holds all 11 top categories, 33 groups and 126 leaves.)

Fields

| Field | On | Meaning |
|---|---|---|
| `code` | all | Stable public identifier. Never renamed, never reused. Spec 006 sends a leaf's `code` with an entry. |
| `translation_key` | all | `categories.<code>.label`. Missing translation: show the parent's name, and "Other" for an unknown leaf (R7). |
| `emoji` | all | A string; `null` only on some leaves (holidays), where the group's emoji stands for them. |
| `retired` | all | `true` when this category or one of its parents was taken out of the list. Show it on old entries; never offer it for a new one. |
| `type` | tops | `expense`, `income` or `saving`. Its groups and leaves share it. |
| `groups` | tops | The top's groups, in order. Never empty. |
| `leaves` | groups | The group's leaves, in order. Never empty; the last one is the group's "Other". |
| `is_other` | leaves | `true` for "Other" leaves: picking one needs a typed name (spec 006). |

The tree is sent whole; it is not paginated.

Response `304`

When `If-None-Match` matches the current `ETag`. No body. The app keeps showing its cached copy.

Errors

| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | no session or token |
| 429 | `request.throttled` | more than 60 requests in a minute; `Retry-After` header |
