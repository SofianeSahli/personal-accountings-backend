# 002 — Accounts: Tasks

> Status: Done (2026-11-16). Implemented and verified; deviations D1–D11 approved by the owner on 2026-11-16.
> Design: [design.md](./design.md) · Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

Order: migrations/models → actions → controllers/requests/resources → routes → docs.
Each task: its tests first (named `REQ-x.y …`), then the code; `php artisan test`, Pint and Larastan pass.

- [x] **T1** — Wiring: dependency, config, provider, CSRF code, queue worker
  - Fulfils: REQ-6.4, 6.5, 6.6, 9.4, 10.1, 10.2
  - Files: `composer.json` (`firebase/php-jwt`), `config/{identity,sanctum,cors,session}.php`, `.env`, `.env.example`, `bootstrap/app.php` (`statefulApi()`), `bootstrap/providers.php`, `IdentityServiceProvider` (migrations, views, translations, routes, limiters, Google binding), `Support/AuthTestMode`, `ApiErrorRenderer` (419 arm), `phpstan.neon` (module migrations, casts), `phpunit.xml` (secure cookie), spec 001 `contract.md`, `docker-compose.yml` (`queue` service)
  - Verify: `AuthTestModeTest`; `ErrorRenderingTest` REQ-6.5; `.env`/`.env.example` key sets equal; `docker compose ps` shows `queue`

- [x] **T2** — Tables and models
  - Fulfils: REQ-1.2, 5.7, 6.3, 9.1 (data side)
  - Files: 5 migrations, `User`, `EmailCode`, `PendingSignIn`, `PersonalAccessToken`, `EmailCodePurpose`, `Locale`, `UserFactory`
  - Verify: `migrate:fresh`; `SHOW CREATE TABLE` shows `CHAR(36)` ascii keys; model tests (hidden hashes, casts)

- [x] **T3** — Email codes and the mail
  - Fulfils: REQ-1.2, 1.6, 1.7, 9.2, 9.3, 9.4, 10.1
  - Files: `GenerateEmailCode`, `SendEmailCode`, `ConsumeEmailCode`, `SpendTimeOfSendingCode`, `EmailCodeMail`, view, `resources/lang/{fr,en}/mail.php`
  - Verify: `EmailCodeMailTest` (FR and EN subject/text, code and minutes, encrypted on the queue); code-store tests

- [x] **T4** — Finishing a sign-in in both modes
  - Fulfils: REQ-6.1–6.3, 6.7, 7.1
  - Files: `Support/{SignInResult,SignInMode,SignInTarget}`, `IssueDeviceSession`, `StartWebSession`, `FinishSignIn` (without two-step branch yet), `AccountResource`, `SessionResource`, `Tests/Support` (`IdentityScenario`, `WebBrowser`, `EnforcedCsrf`)
  - Verify: `IssueDeviceSessionTest`, `DeviceSessionTest`, `WebSessionTest` (cookie flags, regenerated id, no token in body)

- [x] **T5** — Email sign-up
  - Fulfils: REQ-1.1–1.11, 8.2, 8.3, 9.1
  - Files: `RegisterWithEmail`, `ConfirmEmail`, `ResendEmailCode`, `Support/NewAccount`, `EmailSignUpController`, `RegisterRequest`, `ConfirmEmailRequest`, `EmailAddressRequest`, `IdentityRules`, `InternationalPhone`, `BcryptLimit`, routes
  - Verify: `EmailSignUpTest` (both modes for confirm), `InternationalPhoneTest`

- [x] **T6** — Password sign-in
  - Fulfils: REQ-2.1–2.6, 8.1
  - Files: `SignInWithPassword`, `CheckPassword`, `RunCountedAttempt`, `PasswordSignInController`, `PasswordSignInRequest`, route
  - Verify: `PasswordSignInTest` (both modes)

- [x] **T7** — Password reset
  - Fulfils: REQ-3.1–3.4
  - Files: `RequestPasswordReset`, `ResetPassword`, `PasswordResetController`, `ResetPasswordRequest`, routes
  - Verify: `PasswordResetTest` (ends tokens **and** web sessions)

- [x] **T8** — Google sign-in
  - Fulfils: REQ-4.1–4.7, 10.1
  - Files: `Google/*`, `SignInWithGoogle`, `GoogleSignInController`, `GoogleSignInRequest`, route
  - Verify: `GoogleIdentityVerifierTest`, `GoogleSignInTest` (both modes)

- [x] **T9** — Two-step sign-in and its settings
  - Fulfils: REQ-5.1–5.7
  - Files: `FinishSignIn` (two-step branch), `ConfirmSignInCode`, `ResendSignInCode`, `LockLivePendingSignIn`, `SendSignInCode`, `EnableTwoFactor`, `SendTwoFactorDisableCode`, `DisableTwoFactor`, `TwoFactorSignInController`, `TwoFactorController`, requests, routes
  - Verify: `TwoFactorSignInTest` (incl. web-started pending refused by a token client), `TwoFactorManagementTest`

- [x] **T10** — Account and sign-out
  - Fulfils: REQ-7.1, 7.2, 6.7, 8.4
  - Files: `AccountController`, `LogoutController`, `SignOutDevice`, routes
  - Verify: `AccountTest`, logout tests in both modes, `CorsTest`, `NoPersonalDataInLogsTest`; `route:list --path=api` matches the contract

- [x] **T11** — Docs
  - Fulfils: owner decisions
  - Files: CLAUDE.md (Authentication: same paths for both modes, Google and two-step in scope, stateful-domains warning), README (auth setup, env keys, queue worker), spec 001 `tasks.md` status (approved, merged; CI passed on PR #1)
  - Verify: review

## Progress

- **2026-11-14:** code for T1–T10 written; nothing run yet (Docker was down).
- **2026-11-16:** `firebase/php-jwt` installed, `migrate:fresh` run, every test renamed `REQ-x.y …` and rewritten in the function style of spec 001 (`Pest\Laravel\postJson`, no `$this` state), missing tests written, T11 docs done. Tests, Pint, Larastan and `composer audit` pass.

Fixes found on the first run (tests only, no behaviour change):
- `GoogleIdentityVerifierTest` signed tokens for an audience no config listed; it now sets `identity.google_client_ids` itself.
- `GoogleSignInTest` (REQ-4.7 race) inserted a raw user without an `id`; it now gives one.
- `TwoFactorManagementTest`: an integer `code` gives `code.string`, as the contract says (the test expected `code.digits`).

## Deviations from spec
(Record any change made during implementation and when it was approved.)

Approved by the owner on 2026-11-16:
- **D1** — New action `OpenSession` (token or web session for a `SignInTarget`), shared by `FinishSignIn` and `ConfirmSignInCode`, so the mode branch is written once.
- **D2** — `Support/GoogleSignUp` DTO (terms version, phone, locale) for `SignInWithGoogle`, next to `NewAccount`.
- **D3** — `SignOutDevice` picks the mode from the current credential (a stored token is deleted; otherwise the web session ends), not from the request origin.
- **D4** — `ConsumeEmailCode::handle` returns the `User` it checked, so callers get a non-null user.
- **D5** — Test helpers live in `Tests/Support` as classes (`IdentityScenario`, `WebBrowser`, `EnforcedCsrf`); `tests/Pest.php` holds no helper. (Replaces the earlier global `fakeDeviceId()`, which Larastan could not see.)
- **D6** — Authenticated controllers take the user with `#[CurrentUser] User $user` instead of `$request->user()`.
- **D7** — The CORS config also exposes `X-Request-Id` to the browser.
- **D8** — The architecture helper `SourceFiles` skips `*.blade.php`: a Blade view cannot start with `declare(strict_types=1);`.
- **D9** — `phpstan.neon` gains `databaseMigrationsPath` (module migrations) and `parseModelCastsMethod: true`, so Larastan types model columns from the migrations and casts. No path is excluded and no error is ignored.
- **D10** — `phpunit.xml` forces `SESSION_SECURE_COOKIE=true`, so the cookie test does not depend on a developer's local `.env` (which is `false` for http).
- **D11** — `EnforcedCsrf` (test support) keeps Laravel's CSRF check on under tests (Laravel skips it there); `WebBrowser` swaps it in through `sanctum.middleware.validate_csrf_token`. Production uses Laravel's `ValidateCsrfToken` unchanged.

## Verification report
| REQ | Verified how | Result |
|---|---|---|
| 1.1–1.11 | `EmailSignUpTest`, `InternationalPhoneTest`, `WebSessionTest` (confirm in cookie mode) | Pass |
| 2.1–2.6 | `PasswordSignInTest`, `WebSessionTest` | Pass |
| 3.1–3.4 | `PasswordResetTest`, `WebSessionTest` (reset ends web sessions), `TwoFactorSignInTest` (reset ends pending sign-ins) | Pass |
| 4.1–4.7 | `GoogleIdentityVerifierTest`, `GoogleSignInTest`, `WebSessionTest` | Pass |
| 5.1–5.7 | `TwoFactorSignInTest`, `TwoFactorManagementTest`, `WebSessionTest` (mode mismatch refused both ways) | Pass |
| 6.1–6.7 | `WebSessionTest` (cookie flags, regenerated id, no token, 419), `DeviceSessionTest` (90-day expiry), `IssueDeviceSessionTest`, `CorsTest`, `ErrorRenderingTest` (419 arm) | Pass |
| 7.1–7.2 | `AccountTest`, `DeviceSessionTest`, `WebSessionTest` | Pass |
| 8.1–8.4 | Throttle tests in each file, `NoPersonalDataInLogsTest` | Pass |
| 9.1–9.4 | `EmailSignUpTest` (locale), `EmailCodeMailTest` (FR/EN), `docker compose ps` shows `queue` | Pass |
| 10.1–10.2 | `AuthTestModeTest`, `GoogleSignInTest`, `EmailSignUpTest` | Pass |
| Data | `migrate:fresh`; `information_schema` shows `CHAR(36)` ascii on every key and foreign key (`sessions.id` is Laravel's session id) | Pass |
| Routes | `route:list --path=api`: 14 Identity routes, as in `contract.md`, plus `health` | Pass |
| Gate | `php artisan test` 308 passed; Pint clean; Larastan level 6 no errors; `composer audit` clean | Pass |
