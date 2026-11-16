# 001 — Foundations: API contract

> Base: `/api/v1` · JSON only · amounts are integers in minor units + `currency` (no amount is exchanged in this spec)

## Rules for every `/api/v1` path

### Headers

- Every response carries `X-Request-Id: <uuid>`. A new id is generated per request; an incoming value is ignored.
- Every response is `Content-Type: application/json`, whatever the request's `Accept`.

### Request bodies (`RequireJson`)

A request **with a body** (raw content, form fields or files) must:
- have a JSON `Content-Type` (`application/json` or `…+json`);
- be valid JSON;
- have an object or array at the top level.

Otherwise:

Response `415`
```json
{"error": {"code": "request.not_json"}}
```

Requests without a body (`GET`, `DELETE`, …) are accepted with any `Content-Type`.

### Error envelope

```json
{"error": {"code": "<domain>.<reason>"}}
```

Optional members, present only when they apply:

| Member | When | Example |
|---|---|---|
| `fields` | `validation.failed` | `{"amount": ["amount.required"], "items.0.amount": ["items.amount.integer"]}` |
| `retry_after_seconds` | `request.throttled` (also sent as the `Retry-After` header) | `42` |
| `debug` | `server.error` **only when `APP_DEBUG=true`** | `{"exception": "…", "message": "…", "file": "…", "line": 12, "trace": ["…:12"]}` |

`fields` keys are the failing input paths. Values are codes of the form `<field path without numeric indexes>.<rule in snake_case>`. The body never contains translated messages.

Response `422` (example)
```json
{
  "error": {
    "code": "validation.failed",
    "fields": {
      "amount": ["amount.required"],
      "items.0.amount": ["items.amount.integer"]
    }
  }
}
```

### Global error codes

| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | Route needs authentication and none is valid (from spec 002 on) |
| 403 | `auth.forbidden` | Authorization refused |
| 404 | `request.not_found` | Unknown path (including the removed prototype paths, e.g. `/api/v1/categories`, `/api/v1/money-transition`, `/api/v1/profile`) or missing model |
| 405 | `request.method_not_allowed` | Path exists, method doesn't |
| 415 | `request.not_json` | See *Request bodies* |
| 419 | `auth.csrf_mismatch` | Web (cookie) request with a missing or wrong CSRF token (added by spec 002) |
| 422 | `validation.failed` | With `fields` |
| 429 | `request.throttled` | With `retry_after_seconds` and `Retry-After` |
| 500 | `server.error` | Unexpected failure; no details unless `APP_DEBUG=true` |
| 503 | `server.unavailable` | Maintenance mode |

## GET /api/v1/health

Auth: none · Policy: none · Rate limit: 60 requests / minute / IP (file store)

Request: no body, no query parameters.

Response `200`
```json
{"data": {"status": "ok"}}
```

Errors

| Status | Code | When |
|---|---|---|
| 405 | `request.method_not_allowed` | `POST`, `PUT`, `PATCH`, `DELETE` |
| 429 | `request.throttled` | More than 60 requests in a minute from the same IP |
| 503 | `health.database_unavailable` | MySQL does not answer |

The response never includes versions, host names, configuration or timings.

## Removed

| Method | Path | Now |
|---|---|---|
| GET | `/up` | 404 (Laravel's built-in health route, replaced by `/api/v1/health`) |
| * | `/api/v1/profile`, `/api/v1/profile/{user}` | 404 `request.not_found` |
| GET | `/api/v1/categories` | 404 `request.not_found` |
| * | `/api/v1/money-transition`, `/api/v1/money-transition/{id}`, `/api/v1/money-transition/summary` | 404 `request.not_found` |
