# 002 — Accounts: Tasks

> Status: Approved (2026-11-14)
> Design: [design.md](./design.md) · Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

Order: migrations/models → actions → controllers/requests/resources → routes → docs.
Each task: port Shop Keeper's tests for it first (renamed `REQ-x.y …`), then the code; `php artisan test`, Pint and Larastan pass.

- [ ] **T1** — Wiring: dependency, config, provider, CSRF code, queue worker
  - Fulfils: REQ-6.4, 6.5, 6.6, 9.4, 10.1, 10.2
  - Files: `composer.json` (`firebase/php-jwt`), `config/{identity,sanctum,cors,session}.php`, `.env`, `.env.example`, `bootstrap/app.php` (`statefulApi()`), `bootstrap/providers.php`, `IdentityServiceProvider` (migrations, views, translations, routes, limiters, Google binding), `Support/AuthTestMode`, `ApiErrorRenderer` (419 arm), spec 001 `contract.md`, `docker-compose.yml` (`queue` service)
  - Verify: `AuthTestModeTest`; `ErrorRenderingTest` REQ-6.5; `.env`/`.env.example` key sets equal; `docker compose ps` shows `queue`

- [ ] **T2** — Tables and models
  - Fulfils: REQ-1.2, 5.7, 6.3, 9.1 (data side)
  - Files: 5 migrations, `User`, `EmailCode`, `PendingSignIn`, `PersonalAccessToken`, `EmailCodePurpose`, `Locale`, `UserFactory`
  - Verify: `migrate:fresh`; `SHOW CREATE TABLE` shows `CHAR(36)` ascii keys; model tests (hidden hashes, casts)

- [ ] **T3** — Email codes and the mail
  - Fulfils: REQ-1.2, 1.6, 1.7, 9.2, 9.3, 9.4, 10.1
  - Files: `GenerateEmailCode`, `SendEmailCode`, `ConsumeEmailCode`, `SpendTimeOfSendingCode`, `EmailCodeMail`, view, `resources/lang/{fr,en}/mail.php`
  - Verify: `EmailCodeMailTest` (FR and EN subject/text, code and minutes, encrypted on the queue); code-store tests

- [ ] **T4** — Finishing a sign-in in both modes
  - Fulfils: REQ-6.1–6.3, 6.7, 7.1
  - Files: `Support/{SignInResult,SignInMode,SignInTarget}`, `IssueDeviceSession`, `StartWebSession`, `FinishSignIn` (without two-step branch yet), `AccountResource`, `SessionResource`, `Tests/Support` helpers
  - Verify: `IssueDeviceSessionTest`, `DeviceSessionTest`, `WebSessionTest` (cookie flags, regenerated id, no token in body)

- [ ] **T5** — Email sign-up
  - Fulfils: REQ-1.1–1.11, 8.2, 8.3, 9.1
  - Files: `RegisterWithEmail`, `ConfirmEmail`, `ResendEmailCode`, `Support/NewAccount`, `EmailSignUpController`, `RegisterRequest`, `ConfirmEmailRequest`, `EmailAddressRequest`, `IdentityRules`, `InternationalPhone`, `BcryptLimit`, routes
  - Verify: `EmailSignUpTest` (both modes for confirm), `InternationalPhoneTest`

- [ ] **T6** — Password sign-in
  - Fulfils: REQ-2.1–2.6, 8.1
  - Files: `SignInWithPassword`, `CheckPassword`, `RunCountedAttempt`, `PasswordSignInController`, `PasswordSignInRequest`, route
  - Verify: `PasswordSignInTest` (both modes)

- [ ] **T7** — Password reset
  - Fulfils: REQ-3.1–3.4
  - Files: `RequestPasswordReset`, `ResetPassword`, `PasswordResetController`, `ResetPasswordRequest`, routes
  - Verify: `PasswordResetTest` (ends tokens **and** web sessions)

- [ ] **T8** — Google sign-in
  - Fulfils: REQ-4.1–4.7, 10.1
  - Files: `Google/*`, `SignInWithGoogle`, `GoogleSignInController`, `GoogleSignInRequest`, route
  - Verify: `GoogleIdentityVerifierTest`, `GoogleSignInTest` (both modes)

- [ ] **T9** — Two-step sign-in and its settings
  - Fulfils: REQ-5.1–5.7
  - Files: `FinishSignIn` (two-step branch), `ConfirmSignInCode`, `ResendSignInCode`, `LockLivePendingSignIn`, `SendSignInCode`, `EnableTwoFactor`, `SendTwoFactorDisableCode`, `DisableTwoFactor`, `TwoFactorSignInController`, `TwoFactorController`, requests, routes
  - Verify: `TwoFactorSignInTest` (incl. web-started pending refused by a token client), `TwoFactorManagementTest`

- [ ] **T10** — Account and sign-out
  - Fulfils: REQ-7.1, 7.2, 6.7, 8.4
  - Files: `AccountController`, `LogoutController`, `SignOutDevice`, routes
  - Verify: `AccountTest`, logout tests in both modes, `NoPersonalDataInLogsTest`; `route:list --path=api` matches the contract

- [ ] **T11** — Docs
  - Fulfils: owner decisions
  - Files: CLAUDE.md (Authentication: same paths for both modes, Google and two-step in scope, stateful-domains warning), README (auth setup, env keys, queue worker), spec 001 `tasks.md` status (approved, merged; CI passed on PR #1)
  - Verify: review

## Progress (2026-11-14)

The owner asked to copy the code first and verify it in a later session (Docker was not running). Code for T1–T10 is written but **nothing has run yet**, so no task is ticked.

Still to do before ticking T1–T10:
1. `docker compose exec personal-accounting-backend composer require firebase/php-jwt` (`GoogleIdentityVerifier` needs it).
2. `migrate:fresh`, then the tests, Pint and Larastan. If Larastan does not infer model properties from `app/Modules/*/database/migrations`, add those paths to `phpstan.neon` (`databaseMigrationsPath`).
3. The Shop Keeper tests were copied with mechanical fixes only: namespaces, `identity.*` config, `<field>.<rule>` codes, strict types, no `shop`. They still need `REQ-x.y` names and fixes from the first run.
4. Tests not written yet: `WebSessionTest` (cookie mode in every sign-in, logout and reset), `CorsTest`, `EmailCodeMailTest` (FR/EN), `NoPersonalDataInLogsTest`, the `ErrorRenderingTest` 419 case, `locale` validation, and `TwoFactorSignInTest` for a web-started sign-in refused by a token client.
5. T11 docs.

## Deviations from spec
(Record any change made during implementation and when it was approved.)

Not yet approved by the owner:
- **D1** — New action `OpenSession` (token or web session for a `SignInTarget`), shared by `FinishSignIn` and `ConfirmSignInCode`, so the mode branch is written once.
- **D2** — `Support/GoogleSignUp` DTO (terms version, phone, locale) for `SignInWithGoogle`, next to `NewAccount`.
- **D3** — `SignOutDevice` picks the mode from the current credential (a stored token is deleted; otherwise the web session ends), not from the request origin.
- **D4** — `ConsumeEmailCode::handle` returns the `User` it checked, so callers get a non-null user.
- **D5** — `fakeDeviceId()` lives in `tests/Pest.php` (global Pest helper), not in `Tests/Support`.
- **D6** — Authenticated controllers take the user with `#[CurrentUser] User $user` instead of `$request->user()`.
- **D7** — The CORS config also exposes `X-Request-Id` to the browser.

## Verification report
| REQ | Verified how | Result |
|---|---|---|
