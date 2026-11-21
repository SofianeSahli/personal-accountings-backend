# 004 — Invitations and members: API contract

> Base: `/api/v1` · JSON only · the rules of [spec 001's contract](../001-foundations/contract.md) apply (envelope, `X-Request-Id`, `request.not_json`, `request.throttled`).
> Auth: every route below needs a signed-in user, by session cookie (web) or `Authorization: Bearer <token>` (mobile), see [spec 002](../002-accounts/contract.md#modes). Without one: `401 auth.unauthenticated`.
> Shapes and rules reused from [spec 003's contract](../003-households/contract.md): Household, Member, lists (cursor pagination), not found and forbidden.

## Shared shapes

### Invitation (owner's view)

```json
{
  "id": "0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
  "email": "amira@example.com",
  "created_at": "2026-11-21T09:30:00Z",
  "expires_at": "2026-11-28T09:30:00Z"
}
```

`email` is the invited address, trimmed and lowercased.

### Received invitation (invitee's view)

```json
{
  "id": "0193c4d2-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
  "household": {"id": "0193b0c1-2d3e-7f40-8a1b-2c3d4e5f6a7b", "name": "Foyer Sahli"},
  "invited_by": {"name": "Sofiane"},
  "created_at": "2026-11-21T09:30:00Z",
  "expires_at": "2026-11-28T09:30:00Z"
}
```

`invited_by.name` is the inviter's current account name. No email address is returned.

### New QR invitation (owner, once)

```json
{
  "id": "0193c4e7-5b6c-7d8e-9f0a-1b2c3d4e5f6a",
  "token": "q3Z0bK9xVw2mT7rYc1sLp8uN4eHjA6dGfR5iO0kXyBw",
  "expires_at": "2026-11-21T09:40:00Z"
}
```

`token` is shown only in this answer and never again. The app draws it as a QR code. What the code encodes around the token, for example a link that opens the app, is decided in the frontend spec.

### QR invitation preview

```json
{
  "household": {"id": "0193b0c1-2d3e-7f40-8a1b-2c3d4e5f6a7b", "name": "Foyer Sahli"},
  "invited_by": {"name": "Sofiane"},
  "expires_at": "2026-11-21T09:40:00Z"
}
```

### Pending

An invitation is pending until it is accepted, declined or cancelled, it expires (7 days after it was sent), or its household is deleted. Only pending invitations are listed and can be answered or cancelled. Anything else answers like an unknown id:

```json
{"error": {"code": "invitation.not_found"}}
```

A QR invitation is pending until someone joins with it, the owner shows a new one, its 10 minutes pass, or its household is deleted. It works once.

Dates are ISO 8601 in UTC.

### Field codes

| Field | Codes |
|---|---|
| `email` | `email.required`, `email.string`, `email.email`, `email.max` (255) |
| `token` (QR) | `token.required`, `token.string` |

The address is trimmed and lowercased before the checks.

### Order of checks

`401` → `404 household.not_found` → `403 household.forbidden` → `404` of the record in the path → `422` → `429`. On the three QR routes, their throttle (`429`) comes right after `401`.

### Throttling

- `POST households/{household}/invitations`: 20 per household per rolling 24 hours (counted from invitations actually sent).
- `POST households/{household}/qr-invitations`: the shared `creations` limit of spec 003 (10 per minute per account).
- `POST qr-invitations/preview` and `POST qr-invitations/accept`: 10 per minute per account, together.

---

## POST households/{household}/invitations

Owner only. Invites an email address to join the household as a `member`, and sends a notice email to that address in your language. The answer and the email are the same whether or not an account exists for the address.

Request
```json
{"email": "amira@example.com"}
```

Response `201`: `{"data": Invitation}`

Errors
| Status | Code | When |
|---|---|---|
| 404 | `household.not_found` | — |
| 403 | `household.forbidden` | you are a `member` |
| 422 | `validation.failed` | see field codes |
| 422 | `invitation.self` | the address is your own account email |
| 422 | `invitation.already_member` | the address belongs to a member of this household |
| 422 | `invitation.already_pending` | a pending invitation for this address already exists in this household |
| 422 | `household.member_limit` | the household already has 10 members, you included |
| 422 | `invitation.limit_reached` | the household already has 10 pending invitations |
| 429 | `request.throttled` | the household sent 20 invitations in the last 24 hours; `retry_after_seconds` says when the next one is possible |

## GET households/{household}/invitations

Owner only. The household's pending invitations, oldest first.

Response `200`: list of Invitation (owner's view).

Errors: `404 household.not_found`, `403 household.forbidden`.

## DELETE households/{household}/invitations/{invitation}

Owner only. Cancels a pending invitation. No email is sent. To send the notice again, cancel and invite again.

Response `204`, no body.

Errors
| Status | Code | When |
|---|---|---|
| 404 | `household.not_found` | — |
| 403 | `household.forbidden` | you are a `member` |
| 404 | `invitation.not_found` | unknown or malformed id, another household's invitation, or not pending |

## GET invitations

Your pending invitations: those sent to your account email, in any letter case, once that email is confirmed. Oldest first. Still listed while you belong to a household; accepting is what is refused.

Response `200`: list of Received invitation. Empty when you have none or your email is not confirmed.

## POST invitations/{invitation}/accept

Joins the household as a `member`.

Request: no body.

Response `200`: `{"data": Household}`, with `"role": "member"`. From now on the household is in `GET households`, you are in its members list, and you reach its book.

Errors
| Status | Code | When |
|---|---|---|
| 404 | `invitation.not_found` | unknown or malformed id, sent to another address, your email is not confirmed, or not pending (expired, cancelled, already answered, household deleted) |
| 422 | `household.already_joined` | you already belong to a household; leave it first. The invitation stays pending |
| 422 | `household.member_limit` | the household already has 10 members. The invitation stays pending |

## POST invitations/{invitation}/decline

Declines the invitation. The owner sees it leave their pending list.

Request: no body.

Response `204`, no body.

Errors: `404 invitation.not_found` (same cases as accept).

## DELETE households/{household}/members/{user}

Owner only. Removes a member. `{user}` is the member's account `id` from the members list. The member loses access to the household and its book at once, and is free to join or create another household.

Response `204`, no body.

Errors
| Status | Code | When |
|---|---|---|
| 404 | `household.not_found` | — |
| 403 | `household.forbidden` | you are a `member` |
| 404 | `member.not_found` | unknown or malformed id, not a member of this household, or already gone |
| 422 | `household.owner_cannot_leave` | `{user}` is the owner (you). Delete the household instead |

## POST households/{household}/leave

Member only. Leaves the household. You lose access to the household and its book at once, and are free to join or create another household.

Request: no body.

Response `204`, no body.

Errors
| Status | Code | When |
|---|---|---|
| 404 | `household.not_found` | — |
| 422 | `household.owner_cannot_leave` | you are the owner. Delete the household instead (`DELETE households/{household}`) |

## POST households/{household}/qr-invitations

Owner only. Creates a QR invitation, valid once for 10 minutes, and cancels the household's previous unused one. Sends no email. Does not count toward the 10 pending email invitations or the 20 per day.

Throttle: creations.

Request: no body.

Response `201`: `{"data": New QR invitation}`

Errors
| Status | Code | When |
|---|---|---|
| 429 | `request.throttled` | — |
| 404 | `household.not_found` | — |
| 403 | `household.forbidden` | you are a `member` |
| 422 | `household.member_limit` | the household already has 10 members, you included |

## POST qr-invitations/preview

Shows which household a scanned QR code invites you to. Changes nothing. Your email must be confirmed.

Throttle: 10 per minute per account, shared with `POST qr-invitations/accept`.

Request
```json
{"token": "q3Z0bK9xVw2mT7rYc1sLp8uN4eHjA6dGfR5iO0kXyBw"}
```

Response `200`: `{"data": QR invitation preview}`

Errors
| Status | Code | When |
|---|---|---|
| 429 | `request.throttled` | — |
| 422 | `validation.failed` | see field codes |
| 404 | `invitation.not_found` | unknown token, already used, replaced by a newer QR code, expired, household deleted, or your email is not confirmed |

## POST qr-invitations/accept

Joins the household of a scanned QR code as a `member`. The QR code then stops working.

Throttle: 10 per minute per account, shared with `POST qr-invitations/preview`.

Request
```json
{"token": "q3Z0bK9xVw2mT7rYc1sLp8uN4eHjA6dGfR5iO0kXyBw"}
```

Response `200`: `{"data": Household}`, with `"role": "member"`.

Errors
| Status | Code | When |
|---|---|---|
| 429 | `request.throttled` | — |
| 422 | `validation.failed` | see field codes |
| 404 | `invitation.not_found` | as for preview |
| 422 | `household.already_joined` | you already belong to a household; leave it first. The QR code stays valid |
| 422 | `household.member_limit` | the household already has 10 members. The QR code stays valid |

## Changed: DELETE households/{household}

As in spec 003, and it also cancels the household's pending invitations, email and QR. They then answer `404 invitation.not_found`.

## Notice email

Sent once per invitation, from the queue, in the inviter's language (`fr` or `en`). Plain text. It names the inviter and the household, says to sign in or sign up with this address to see the invitation in the app, and that it expires in 7 days. It contains no link token and no code.
