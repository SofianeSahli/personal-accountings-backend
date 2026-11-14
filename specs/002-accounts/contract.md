# 002 — Accounts: API contract

> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).

## Modes

| | Web (cookie) | Mobile (token) |
|---|---|---|
| Who | Requests from a domain in `SANCTUM_STATEFUL_DOMAINS` | Any other client |
| Before signing in | `GET /sanctum/csrf-cookie` (204, sets `XSRF-TOKEN`); then send `X-XSRF-TOKEN` and cookies on every request | — |
| `device_id` on sign-in | Not used | Required, UUID chosen by the app per install |
| Signed-in answer | Session cookie; body has no `token` | Body has `token` |
| Authenticate | Session cookie | `Authorization: Bearer <token>` |
| Expiry | `SESSION_LIFETIME` of inactivity | 90 days after issue |

CSRF failure (web mode only):

Response `419`
```json
{"error": {"code": "auth.csrf_mismatch"}}
```

## Shared shapes

### Account

```json
{
  "id": "0193a2f4-7c1e-7d2a-9f3b-1c2d3e4f5a6b",
  "name": "Sofiane",
  "email": "user@example.com",
  "phone": "+21620123456",
  "locale": "fr",
  "two_factor_enabled": false
}
```

`phone` may be `null`. `locale` is `fr` or `en`.

### Session (answer of every sign-in)

Signed in, mobile — `200`
```json
{"data": {"status": "signed_in", "token": "0193…|Xk3…", "account": { "…": "Account" }}}
```

Signed in, web — `200`
```json
{"data": {"status": "signed_in", "account": { "…": "Account" }}}
```

Two-step code required — `200`
```json
{"data": {"status": "code_required", "pending_sign_in": "9f2c…(80 hex chars)", "expires_in_seconds": 600}}
```

Then call `POST auth/two-factor`.

### Field codes

Validation errors are `422 validation.failed` with `fields`, e.g.:
```json
{"error": {"code": "validation.failed", "fields": {"password": ["password.min"], "device_id": ["device_id.required"]}}}
```

| Field | Codes |
|---|---|
| `name` | `name.required`, `name.string`, `name.max` (120) |
| `email` | `email.required`, `email.string`, `email.email`, `email.max` (255) |
| `password` (new) | `password.required`, `password.string`, `password.min` (8), `password.bcrypt_limit` (72 bytes) |
| `password` (sign-in) | `password.required`, `password.string` |
| `terms_version` | `terms_version.required`, `terms_version.string`, `terms_version.in` (not the current version) |
| `phone` | `phone.international_phone` |
| `locale` | `locale.enum` |
| `device_id` | `device_id.required` (mobile only), `device_id.string`, `device_id.uuid` |
| `code` | `code.required`, `code.string`, `code.digits` (6) |
| `pending_sign_in` | `pending_sign_in.required`, `pending_sign_in.string` |
| `id_token` | `id_token.required`, `id_token.string` |

Emails are lowercased before validation. Phones are saved in E.164.

### Throttling

Sign-in routes: 30/min per network origin. Email routes: 10/min per network origin, and 3 per 15 min per address and per account across all email routes.

Response `429`
```json
{"error": {"code": "request.throttled", "retry_after_seconds": 42}}
```

---

## POST auth/email/register
Auth: none · Throttle: email

Request
```json
{"name": "Sofiane", "email": "user@example.com", "password": "correct horse", "terms_version": "2026-11", "phone": "+216 20 123 456", "locale": "fr"}
```
`phone` and `locale` are optional (`locale` defaults to `fr`).

Response `202`, no body. A 6-digit code is emailed (valid 15 min).

Errors
| Status | Code | When |
|---|---|---|
| 409 | `email.taken` | the address has a confirmed account |
| 422 | `validation.failed` | see field codes |

## POST auth/email/resend
Auth: none · Throttle: email

Request `{"email": "user@example.com"}`

Response `202`, no body, for any address. A code is emailed only for an unconfirmed account.

## POST auth/email/confirm
Auth: none · Throttle: sign-in

Request
```json
{"email": "user@example.com", "code": "123456", "device_id": "6f1c…"}
```
`device_id`: mobile only.

Response `200` Session.

Errors
| Status | Code | When |
|---|---|---|
| 422 | `code.invalid` | wrong, expired, used or unknown code or address |
| 422 | `validation.failed` | see field codes |

## POST auth/email/login
Auth: none · Throttle: sign-in

Request
```json
{"email": "user@example.com", "password": "correct horse", "device_id": "6f1c…"}
```

Response `200` Session.

Errors
| Status | Code | When |
|---|---|---|
| 401 | `auth.invalid_credentials` | wrong password, unknown address, or no password on the account |
| 403 | `auth.email_unconfirmed` | right password, unconfirmed address |
| 429 | `auth.password_locked` | 5 wrong passwords in a row; body has `retry_after_seconds` (lock 15 min) |
| 422 | `validation.failed` | see field codes |

## POST auth/password/forgot
Auth: none · Throttle: email

Request `{"email": "user@example.com"}`

Response `202`, no body, for any address. A reset code is emailed only for a confirmed account.

## POST auth/password/reset
Auth: none · Throttle: sign-in

Request
```json
{"email": "user@example.com", "code": "123456", "password": "new correct horse"}
```

Response `204`. Every token and web session of the account is ended; two-step sign-in stays as it was.

Errors
| Status | Code | When |
|---|---|---|
| 422 | `code.invalid` | wrong, expired, used, unknown, or another purpose's code |
| 422 | `validation.failed` | see field codes |

## POST auth/google
Auth: none · Throttle: sign-in

Request
```json
{"id_token": "eyJ…", "device_id": "6f1c…", "terms_version": "2026-11", "phone": null, "locale": "en"}
```
`terms_version` is needed only when the account is created; `phone` and `locale` are used only then.

Response `200` Session.

Errors
| Status | Code | When |
|---|---|---|
| 401 | `auth.google_invalid` | the ID token fails a check |
| 503 | `auth.google_unavailable` | Google's keys cannot be fetched |
| 409 | `auth.google_email_conflict` | the email belongs to an account linked to another Google identity, or Google is not authoritative for it |
| 422 | `auth.terms_required` | first sign-in without `terms_version` |
| 422 | `validation.failed` | see field codes |

## POST auth/two-factor
Auth: none · Throttle: sign-in

Request `{"pending_sign_in": "9f2c…", "code": "123456"}`

Response `200` Session (signed in, in the mode of the sign-in that started it).

Errors
| Status | Code | When |
|---|---|---|
| 422 | `code.invalid` | wrong code (the 5th ends the sign-in) |
| 401 | `auth.pending_invalid` | unknown, used, ended or expired (10 min) sign-in, or a different mode |
| 422 | `validation.failed` | see field codes |

## POST auth/two-factor/resend
Auth: none · Throttle: email

Request `{"pending_sign_in": "9f2c…"}`

Response `202`, no body. New code; the 10 minutes and the try count restart.

Errors: `401 auth.pending_invalid` as above.

## GET me
Auth: session or token

Response `200`
```json
{"data": { "…": "Account" }}
```

Errors: `401 auth.unauthenticated`.

## POST auth/logout
Auth: session or token

Response `204`. Ends only the current token, or only the current web session.

Errors: `401 auth.unauthenticated`.

## POST me/two-factor
Auth: session or token

Response `204`. Two-step sign-in is on; open sessions keep working.

Errors: `409 two_factor.already_enabled`, `401 auth.unauthenticated`.

## POST me/two-factor/disable-code
Auth: session or token · Throttle: email

Response `202`, no body. A disable code is emailed.

Errors: `409 two_factor.not_enabled`, `401 auth.unauthenticated`.

## DELETE me/two-factor
Auth: session or token

Request `{"code": "123456"}`

Response `204`. Two-step sign-in is off; no session ends.

Errors
| Status | Code | When |
|---|---|---|
| 409 | `two_factor.not_enabled` | already off |
| 422 | `code.invalid` | wrong, expired, used, or another purpose's code |
| 401 | `auth.unauthenticated` | no session or token |
| 422 | `validation.failed` | see field codes |
