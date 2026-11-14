# 002 — Accounts: Requirements

> Status: Approved (2026-11-14)
> Scope: new module `app/Modules/Identity`; small additions to `app/Shared` (CSRF error code), config, Docker (queue worker), CLAUDE.md.

## Context

The API has no accounts yet. Every later feature (households, transactions) needs a signed-in user. Shop Keeper's Identity module (`C:\shop-keeper\shop-keeper-backend\app\Modules\Identity`) already implements and tests every account flow this app needs: email sign-up with an emailed code, password sign-in with a lock, password reset by code, Google sign-in, two-step sign-in by emailed code, one token per device, `/me` and logout.

This spec ports that module and adapts it to this repo's rules. It does not depend on Shop Keeper. The adaptations are:

- UUID keys (CLAUDE.md "Data → Keys"), and per-field validation codes generated as `<field>.<rule>` (spec 001).
- A **web cookie mode** next to the mobile token mode, because the Angular app is the first client.
- Device tokens that **expire**.
- Code emails in **French and English**. More languages can be added without code changes beyond an enum case.
- **No `shop` in `/me`.** Households come in spec 003. `Identity` must not depend on `Household`, so `/me` will never embed one; the frontend will call a Household endpoint instead.

### Owner decisions (2026-11-13)

| Topic | Decision |
|---|---|
| Workflow | One short spec (requirements, design, contract, tasks), approved in one pass, then the port. |
| Scope | Everything Shop Keeper's Identity has, including Google sign-in and two-step sign-in. CLAUDE.md is updated to match. |
| Web login | Both modes now: cookie session for the web, token per device for mobile. |
| Mode selection | Same paths for both modes. A request from a stateful web origin (`SANCTUM_STATEFUL_DOMAINS`, Sanctum's CSRF cookie) gets a **session**; any other request must send `device_id` and gets a **token**. |
| Token lifetime | 90 days (`SANCTUM_TOKEN_EXPIRATION`, in minutes, in `.env`). |
| Email language | French and English to start, through translation files, so adding a language means adding files. The API itself sends only keys (`error.code`, field codes); the frontend translates them. |
| Branch | `feat/002-accounts`, from `main` after spec 001 merged. |
| No shared package (2026-11-14) | The Identity extraction brief (`Claude outputs/identity-extraction-brief.md`) is dropped. No `laravel-identity` or `laravel-api-kit` package and no new repo: the code is copied into this repo, and Shop Keeper is not changed. Terms acceptance stays in Identity. Google and two-step sign-in are always on. MySQL 8.4 only. |

## User stories

- **US-1** — As a visitor, I want to sign up with my email and a password and confirm the address with an emailed code, so that I own an account.
- **US-2** — As a user, I want to sign in with my email and password, so that I reach my data on the web and on my phone.
- **US-3** — As a user who forgot my password, I want to reset it with an emailed code, so that I regain access.
- **US-4** — As a user, I want to sign in with Google, so that I don't need a password.
- **US-5** — As a user, I want two-step sign-in by emailed code, so that a stolen password is not enough.
- **US-6** — As a web user, I want my browser session kept in a secure cookie; as a mobile user, I want one token per device, so that each client signs in the way it can.
- **US-7** — As a user, I want to see my account and sign out of the current device or browser.
- **US-8** — As the owner, I want sign-in and email routes rate limited and their answers generic, so that the app resists guessing and does not reveal who has an account.
- **US-9** — As a user, I want code emails in my language (French or English).
- **US-10** — As the owner, I want a test mode for automated clients, which can never be on in production.

## Acceptance criteria (EARS)

Codes below are API error codes. Field codes follow `<field>.<rule>` (spec 001, REQ-6.3).

### US-1 — Email sign-up

- **REQ-1.1** WHEN a visitor posts a valid name (≤ 120 chars), email (≤ 255, lowercased), password (8 chars to 72 bytes), the current `terms_version`, an optional phone and an optional `locale` THE SYSTEM SHALL create an unconfirmed account, email a 6-digit code, and answer `202` with no session.
- **REQ-1.2** THE SYSTEM SHALL store only a hash of the code. A code lives 15 minutes and is single-use.
- **REQ-1.3** WHEN the address belongs to an **unconfirmed** account THE SYSTEM SHALL replace its name, password, phone, locale and code.
- **REQ-1.4** IF the address belongs to a **confirmed** account THEN THE SYSTEM SHALL answer `409 email.taken` and change nothing.
- **REQ-1.5** WHEN the visitor posts the address and the right code THE SYSTEM SHALL confirm the address and sign in (REQ-6.x).
- **REQ-1.6** IF the code is wrong, expired, used, or the address unknown THEN THE SYSTEM SHALL answer `422 code.invalid`, identically in every case.
- **REQ-1.7** WHEN a code receives its 5th wrong try THE SYSTEM SHALL delete it.
- **REQ-1.8** WHEN a resend is asked for an unconfirmed account THE SYSTEM SHALL email a new code. For any other address it SHALL send nothing and answer the same `202`, taking similar time.
- **REQ-1.9** IF `terms_version` is missing or not the current one THEN THE SYSTEM SHALL answer `422` with `terms_version.required` or `terms_version.in`.
- **REQ-1.10** THE SYSTEM SHALL store a phone in E.164 form (`+21620123456`), accepting spaces, dashes, brackets and a `00` prefix; anything else gives `phone.international_phone`.
- **REQ-1.11** IF a field is missing, of the wrong type or invalid THEN THE SYSTEM SHALL answer `422 validation.failed` with a code per field, never text.

### US-2 — Password sign-in

- **REQ-2.1** WHEN a user posts a confirmed email and the right password THE SYSTEM SHALL sign in (REQ-6.x).
- **REQ-2.2** IF the password is wrong, or the address unknown, or the account has no password (Google-only) THEN THE SYSTEM SHALL answer `401 auth.invalid_credentials`, identically and in similar time (dummy hash check).
- **REQ-2.3** IF the password is right but the address unconfirmed THEN THE SYSTEM SHALL answer `403 auth.email_unconfirmed`.
- **REQ-2.4** WHEN an account reaches 5 consecutive wrong passwords THE SYSTEM SHALL lock password sign-in for 15 minutes and answer `429 auth.password_locked` with `retry_after_seconds`, even for the right password.
- **REQ-2.5** WHEN a sign-in succeeds THE SYSTEM SHALL reset the count. Attempts made during a lock SHALL not extend it.
- **REQ-2.6** THE SYSTEM SHALL compare addresses in lowercase.

### US-3 — Password reset

- **REQ-3.1** WHEN a reset is asked for a confirmed account THE SYSTEM SHALL email a reset code. For an unknown or unconfirmed address it SHALL send nothing and answer the same `202` in similar time.
- **REQ-3.2** WHEN the address, the right reset code and a valid new password are posted THE SYSTEM SHALL set the password, lift any password lock, revoke **every** token and web session of the account, cancel its pending two-step sign-ins, keep two-step sign-in on, and answer `204`.
- **REQ-3.3** IF the code is wrong, expired, used, unknown, or of another purpose THEN THE SYSTEM SHALL answer `422 code.invalid` (REQ-1.6, 1.7 apply).
- **REQ-3.4** A Google-only account SHALL be able to set a password this way.

### US-4 — Google sign-in

- **REQ-4.1** WHEN a client posts a Google ID token THE SYSTEM SHALL verify its signature (Google's keys, cached for their `max-age`), issuer, audience (one of `GOOGLE_CLIENT_IDS`), expiry (60 s leeway) and `email_verified`.
- **REQ-4.2** IF the token fails any check THEN THE SYSTEM SHALL answer `401 auth.google_invalid`; IF Google's keys cannot be fetched THEN `503 auth.google_unavailable`.
- **REQ-4.3** WHEN the Google identity is linked to an account THE SYSTEM SHALL sign it in and refresh its name (keeping the saved name when Google sends none, and never changing the email).
- **REQ-4.4** WHEN no account has the identity or the email THE SYSTEM SHALL create a confirmed account, IF `terms_version` is given; otherwise answer `422 auth.terms_required`.
- **REQ-4.5** WHEN the email matches an account not linked to Google AND Google is authoritative for the address (`@gmail.com` or a hosted domain) THE SYSTEM SHALL link a confirmed account, or replace an unconfirmed sign-up with a new Google account.
- **REQ-4.6** IF the email matches an account linked to another Google identity, or Google is not authoritative for it THEN THE SYSTEM SHALL answer `409 auth.google_email_conflict`.
- **REQ-4.7** Two concurrent first sign-ins of one identity SHALL end with one account.

### US-5 — Two-step sign-in

- **REQ-5.1** WHEN a signed-in user turns two-step on THE SYSTEM SHALL record it and keep existing sessions; IF already on THEN `409 two_factor.already_enabled`.
- **REQ-5.2** WHILE two-step is on, WHEN any sign-in (password, email confirmation, Google) passes THE SYSTEM SHALL not sign in but answer `status: code_required` with an opaque `pending_sign_in` and `expires_in_seconds: 600`, and email a 6-digit code.
- **REQ-5.3** WHEN the pending sign-in and its right code are posted within 10 minutes THE SYSTEM SHALL sign in, in the mode of the original sign-in (same device, or a web session).
- **REQ-5.4** IF the code is wrong THEN `422 code.invalid`; at the 5th wrong code the pending sign-in SHALL end. An unknown, used, ended, expired or other-mode pending sign-in SHALL give `401 auth.pending_invalid`. Wrong codes SHALL not lock the account.
- **REQ-5.5** WHEN a resend is asked for a live pending sign-in THE SYSTEM SHALL send a new code, kill the previous one and restart the 10 minutes and the try count; otherwise `401 auth.pending_invalid` and send nothing.
- **REQ-5.6** WHEN a user asks to turn two-step off THE SYSTEM SHALL email a disable code (`409 two_factor.not_enabled` if off), and turn it off only with that code (REQ-1.6, 1.7 apply), ending no session.
- **REQ-5.7** THE SYSTEM SHALL store only hashes of pending tokens and codes, and never return a code.

### US-6 — Sessions: cookie and token modes

- **REQ-6.1** WHEN a sign-in comes from a stateful web origin (Sanctum stateful domains, with a valid CSRF token) THE SYSTEM SHALL start a web session (regenerated id; cookie `Secure`, `HttpOnly`, `SameSite=Lax`) and answer `status: signed_in` with the account and **no token**.
- **REQ-6.2** WHEN a sign-in comes from any other client THE SYSTEM SHALL require `device_id` (UUID) and answer `status: signed_in` with a token and the account.
- **REQ-6.3** THE SYSTEM SHALL keep one token per account and device (case-insensitive device id), replacing the previous one on a new sign-in and leaving other devices and accounts alone.
- **REQ-6.4** A token SHALL expire 90 days after it is issued (`SANCTUM_TOKEN_EXPIRATION`); an expired token gives `401 auth.unauthenticated`.
- **REQ-6.5** IF a stateful request has a missing or wrong CSRF token THEN THE SYSTEM SHALL answer `419 auth.csrf_mismatch`.
- **REQ-6.6** CORS SHALL allow only the origins in `CORS_ALLOWED_ORIGINS`, with credentials, on `api/*` and `sanctum/csrf-cookie`.
- **REQ-6.7** Every authenticated endpoint SHALL accept both a web session and a bearer token, and answer `401 auth.unauthenticated` without either.

### US-7 — Account and sign-out

- **REQ-7.1** WHEN a signed-in user calls `GET /me` THE SYSTEM SHALL return id, name, email, phone, locale and `two_factor_enabled`, and never a hash or a token.
- **REQ-7.2** WHEN a token client signs out THE SYSTEM SHALL revoke that token only; WHEN a web client signs out THE SYSTEM SHALL end that session only. Both answer `204`.

### US-8 — Rate limits and generic answers

- **REQ-8.1** Sign-in routes (password, email confirm, Google, reset, two-step confirm) SHALL allow 30 requests per minute per network origin, then `429 request.throttled` with `retry_after_seconds`.
- **REQ-8.2** Email-sending routes (register, resend, forgot, two-step resend, disable code) SHALL allow 10 per minute per network origin, and 3 per 15 minutes per address and per account, across routes.
- **REQ-8.3** No answer SHALL reveal whether an account exists, beyond `email.taken` at sign-up (REQ-1.4) and the Google conflict (REQ-4.6).
- **REQ-8.4** Logs SHALL hold no email, name, phone, password, code or token.

### US-9 — Email language

- **REQ-9.1** An account SHALL have a `locale`, one of the supported locales (`fr`, `en`), default `fr`, set at sign-up or at a Google first sign-in; another value gives `locale.enum`.
- **REQ-9.2** WHEN a code email is sent THE SYSTEM SHALL write its subject and text in the account's locale, from the module's translation files, stating the code and its validity in minutes.
- **REQ-9.3** Adding a locale SHALL need only an enum case and a translation folder.
- **REQ-9.4** Code emails SHALL be queued and encrypted on the queue; a queue worker SHALL run in Docker.

### US-10 — Test mode

- **REQ-10.1** WHILE `AUTH_TEST_MODE=true` and the environment is not production THE SYSTEM SHALL use the code `000000` and accept Google ID tokens `test:<email>`.
- **REQ-10.2** IF the environment is production THEN test mode SHALL be off whatever the setting. It is off by default.

Checklist:
- [x] Validation errors (per field, with stable codes)
- [x] Unauthenticated access (401), cookie and token modes
- [ ] Access to another household's data — no household data in this spec
- [x] Rate limits / repeated requests / duplicates
- [ ] Money edge cases — none
- [x] Date & timezone edge cases — expiries are computed in UTC
- [x] Deleting / changing referenced data — reset revokes tokens and sessions

## Non-goals

- Households, `household` in `/me`, invitations (spec 003).
- Changing the name, phone, locale, email or password while signed in; deleting an account. (Each later, by its own spec.)
- Listing or revoking other devices.
- Pruning expired codes, pending sign-ins and tokens on a schedule (no scheduler runs yet).
- A real mail provider; local mail goes to the log.
- Messages in API responses: the API sends keys only.

## Open questions

None. Answered by the owner on 2026-11-13 (see **Owner decisions**).
