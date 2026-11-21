# 004 — Invitations and members: Design

> Status: Approved (2026-11-21)
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview

Everything lives in `Household`: two new tables, `household_invitations` (email) and `household_qr_invitations` (QR), eleven routes, a notice email, one action shared by both ways of joining, and two actions that end a membership. `Identity` gains two pure methods on `User` and one read-only action, because `Household` may neither query `users` nor use `Identity`'s `Locale` enum (arch test REQ-5.5 of spec 001). `Ledger` changes nothing: book access is already read from the live membership at each request (spec 003, REQ-4.2), and one Ledger test proves it.

## Key decisions

| # | Decision | Why |
|---|---|---|
| K1 | An invitation's life is a `status` column (`pending`, `accepted`, `declined`, `cancelled`, enum `InvitationStatus`) plus `expires_at` and `closed_at`. Expiry is not stored: an invitation is **pending** when `status = 'pending'`, `expires_at > now()` and its household is not soft-deleted (scope `HouseholdInvitation::scopePending`, used by every read and write). Rows are never removed or soft-deleted | REQ-8.5, the "pending" definition |
| K2 | `email` is stored trimmed and lowercased, in a `utf8mb4_bin` column, so equality is exact. The default collation (`utf8mb4_0900_ai_ci`) would also ignore accents and match `andré@…` with `andre@…`. `Identity` already lowercases emails on input, and `User::verifiedEmail()` lowercases again | REQ-0.1, REQ-1.6, REQ-3.1 |
| K3 | **One lock per household.** `InviteMember`, `AcceptInvitation` and `DeleteHousehold` start their `DB::transaction` by locking the live household row (`lockForUpdate`, a table `Household` owns). Every limit (pending per email, 10 pending, 10 members, 20 per day) is counted under it, so concurrent requests cannot pass a limit. A unique index cannot express "one pending per email", because pending depends on the clock (K1) | REQ-1.6–1.9, REQ-4.4 |
| K4 | One household per user at accept: `FindMembership` first (clear answer), and spec 003's unique `household_user.active_user_id` as the last word: a `UniqueConstraintViolationException` becomes `422 household.already_joined`, caught outside the transaction like `CreateHousehold` | REQ-4.3 (D9) |
| K5 | Answered at most once. `AcceptInvitation` locks the invitation row after the household row and re-checks that it is pending. `DeclineInvitation` and `CancelInvitation` are a single conditional `UPDATE … WHERE id = ? AND status = 'pending' AND expires_at > now()`; 0 rows changed gives `404 invitation.not_found`. A decline or cancel racing an accept waits on the row lock and then changes nothing | REQ-4.6, REQ-2.4 |
| K6 | `Identity` additions, read-only: `User::verifiedEmail(): ?string` (lowercased email when `email_verified_at` is set, else `null`), `User::localeCode(): string` (`fr` or `en`, `fr` when unset), and the action `Identity\Actions\Accounts\FindAccountIdByEmail` (`string` → `?string`). The two methods are pure, so `Household` reads them on the authenticated user without querying `users` | REQ-1.2, REQ-1.4, REQ-1.5, REQ-3.1, REQ-8.8 |
| K7 | The notice is `Household\Mail\InvitationMail` (`ShouldQueue`, `ShouldBeEncrypted`, `afterCommit()`), a text-only view `household::mail.invitation`, texts in `household::mail` (`fr`, `en`). Sent with `Mail::to($email)->locale($inviter->localeCode())->queue(…)` inside the transaction: `afterCommit` means a rolled-back invitation sends nothing. The subject is fixed text; the inviter's and household's names appear only in the plain-text body. It says "expires in 7 days" rather than a date, because a household has no timezone | REQ-1.2, REQ-1.3, REQ-8.6 |
| K8 | `{invitation}` and `{user}` are not bound implicitly (like spec 003, K8). The raw id is checked with `Str::isUuid` before any query; malformed, unknown and foreign ids then follow the same path to the same `404` | REQ-2.4, REQ-4.2, REQ-5.2 |
| K9 | Policies. `HouseholdPolicy` gains `manageMembers` (owner only, same body as `update` and `delete`), used by the three owner invitation routes and by removing a member. `POST invitations` authorizes in the Form Request's `authorize()`, so `403` comes before `422` (spec 003, K9); the other routes call `Gate::authorize` in the controller. New `InvitationPolicy::answer(User, ?HouseholdInvitation)`: allowed when the invitation is pending (K1) and its `email` equals `$user->verifiedEmail()`, else `denyAsNotFound(code: 'invitation.not_found')` | REQ-8.3, REQ-8.4, REQ-4.2 |
| K10 | The daily limit is counted from the table, under the K3 lock: invitations of the household with `created_at > now() − 24 h`. At 20, `ApiException('request.throttled', 429, retryAfterSeconds: …)`, the seconds until the oldest of them is 24 hours old (at least 1). Laravel's `RateLimiter` is not used: it would count refused attempts and could not see the household before `RequireHousehold` runs | REQ-1.9 (R3) |
| K11 | `DeleteHousehold` (spec 003) now locks the household row first (K3), then soft-deletes the memberships, marks every `pending`-status invitation of the household, email and QR, `cancelled` with `closed_at`, soft-deletes the household and dispatches `HouseholdDeleted` after commit, as before. Cancelling inside the action, not in a listener of `HouseholdDeleted`, keeps it in the same transaction; the event stays the way other modules (Ledger) learn about the deletion. The K1 scope hides those invitations even before | REQ-7.1 (E6) |
| K12 | Ending a membership is a conditional soft delete of one `household_user` row (`WHERE deleted_at IS NULL`); 0 rows changed gives `404 member.not_found`. One table, one row, so no transaction. Spec 003's `active_user_id` becomes `NULL` with `deleted_at`, so the user is free at once | REQ-5.1, REQ-6.1, REQ-6.4 |
| K13 | Dates in responses are `toIso8601ZuluString()` (`2026-11-28T09:30:00Z`) | REQ-2.2, REQ-3.3 |
| K14 | Member count = live `household_user` rows of the household (`SoftDeletes` scope), owner included. `Household::MEMBER_LIMIT = 10`; `HouseholdInvitation::VALID_DAYS = 7`, `PENDING_LIMIT = 10`, `DAILY_LIMIT = 20`; `HouseholdQrInvitation::VALID_MINUTES = 10` | REQ-1.7, REQ-1.8, REQ-4.4, REQ-9.1 (R1) |
| K15 | QR invitations get **their own table**, `household_qr_invitations`, not a `kind` column on `household_invitations`. They have no email, they have a token hash and a different lifetime, and they appear in no list. A shared table would put a `kind` condition in every query and leave `email` nullable. Same status enum (`pending`, `accepted`, `cancelled`) and the same pending scope idea as K1 | REQ-9.6, REQ-10.3 |
| K16 | Token: 32 bytes from `random_bytes`, base64url without padding (43 characters), returned only in the `201` of creation. Stored as `token_hash` = SHA-256 hex (`char(64)` ascii, **unique**), and looked up by that hash (`HouseholdQrInvitation::findByToken`). A 256-bit random token makes guessing and timing attacks pointless, so a hash lookup is enough; nothing can rebuild the token from the database | REQ-9.2 (CLAUDE.md "Hashing") |
| K17 | One pending QR per household: `CreateQrInvitation` runs under the K3 household lock, marks the household's `pending`-status QR rows `cancelled`, checks the member limit, then inserts | REQ-9.3, REQ-9.4 |
| K18 | **One way to join.** `AddMember` (`Household`, `User`) holds the shared rules: `FindMembership` → `household.already_joined`; 10 live members → `household.member_limit`; insert the `member` row. `AcceptInvitation` and `AcceptQrInvitation` call it inside their own transaction, after their locks, and catch the unique violation of K4 outside it. A join rule is written once | REQ-4.3, REQ-4.4, REQ-10.2 |
| K19 | QR preview and join: the token travels in the JSON body, never in the path or query, because URLs end up in access logs and browser history. Rate limiter `household-qr` (10 per minute, keyed by account id), defined in `HouseholdServiceProvider`, on both routes. `POST qr-invitations` for the owner uses `throttle:creations` (spec 003, K12) | REQ-9.1, REQ-10.4, REQ-10.5 (R8, R9) |
| K20 | `QrInvitationPolicy::use(User, ?HouseholdQrInvitation)`: allowed when the QR invitation is pending and `$user->verifiedEmail()` is not `null`, else `denyAsNotFound(code: 'invitation.not_found')`. The controller resolves the row with `findByToken` (`null` when unknown), so every refusal goes through the same denial | REQ-10.1, REQ-10.3 (E11) |

## Placement

| Piece | Path |
|---|---|
| `User::verifiedEmail()`, `User::localeCode()` | `app/Modules/Identity/Models/User.php` |
| `FindAccountIdByEmail` | `app/Modules/Identity/Actions/Accounts/` |
| `HouseholdInvitation`, `HouseholdQrInvitation`, enum `InvitationStatus` (`Pending`, `Accepted`, `Declined`, `Cancelled`); `Household::MEMBER_LIMIT` | `app/Modules/Household/Models/` |
| `InviteMember`, `ListHouseholdInvitations`, `CancelInvitation`, `ListMyInvitations`, `AcceptInvitation`, `DeclineInvitation`, `AddMember`, `CreateQrInvitation`, `PreviewQrInvitation`, `AcceptQrInvitation`, `RemoveMember`, `LeaveHousehold`; `DeleteHousehold` changed | `app/Modules/Household/Actions/` |
| `ReceivedInvitationPage` (page + inviter names), `NewQrInvitation` (row + plain token, returned once), `QrInvitationPreview` (household + inviter name) | `app/Modules/Household/Support/` |
| `InvitationMail` | `app/Modules/Household/Mail/` |
| `mail.php` (`fr`, `en`), `mail/invitation.blade.php` | `app/Modules/Household/resources/lang/…`, `resources/views/…` |
| `HouseholdPolicy::manageMembers`; `InvitationPolicy`; `QrInvitationPolicy` | `app/Modules/Household/Policies/` |
| `HouseholdInvitationController` (`index`, `store`, `destroy`), `InvitationController` (`index`, `accept`, `decline`), `HouseholdQrInvitationController` (`store`), `QrInvitationController` (`preview`, `accept`), `HouseholdMemberController` (+ `destroy`, `leave`) | `app/Modules/Household/Controllers/` |
| `InviteMemberRequest`, `InvitationRules`, `QrTokenRequest` | `app/Modules/Household/Controllers/Requests/` |
| `InvitationResource`, `InvitationList`, `ReceivedInvitationResource`, `ReceivedInvitationList`, `NewQrInvitationResource`, `QrInvitationPreviewResource` | `app/Modules/Household/Controllers/Resources/` |
| Migrations, `HouseholdInvitationFactory`, `HouseholdQrInvitationFactory`, `routes.php`, `HouseholdServiceProvider` (views, translations, both invitation policies, limiter `household-qr`) | each in its usual place |
| Tests | `app/Modules/Household/Tests/` (+ `HouseholdScenario::invite()`); `app/Modules/Identity/Tests/`; `app/Modules/Ledger/Tests/HouseholdBookAccessTest.php` |

`InvitationRules` mirrors `IdentityRules::email()` (`required`, `string`, `email`, `max:255`) and its lowercasing, instead of importing a request helper from another module's `Controllers`.

Cross-module calls:

- `Household` → `Identity`: `FindAccountIdByEmail` (self and already-member checks), `FindAccountNames` (inviter names in the invitee's list), `User::verifiedEmail()` and `User::localeCode()` on the authenticated user, `$user->name` for the notice.
- `Ledger` → `Household`: unchanged (`FindMembership`, `HouseholdDeleted`).
- `Identity` calls nothing above it; `Household` never names `Ledger`. `ModuleMap::ALLOWED` is unchanged (REQ-8.7).

## Data

| Table | Columns | Indexes / FKs |
|---|---|---|
| `household_invitations` | `id` uuid PK; `household_id` uuid; `email` varchar(255) `utf8mb4_bin` (K2); `invited_by` uuid (the owner's account id); `status` varchar(10) ascii (K1); `expires_at` timestamp; `closed_at` timestamp null (when accepted, declined or cancelled); timestamps | index (`household_id`, `status`, `created_at`); index (`email`, `status`); index (`household_id`, `created_at`) (K10); FK `households` restrict; FK `users` (`invited_by`) restrict |

| `household_qr_invitations` | `id` uuid PK; `household_id` uuid; `invited_by` uuid; `token_hash` char(64) ascii (K16); `status` varchar(10) ascii; `expires_at` timestamp; `closed_at` timestamp null (when used or cancelled); timestamps | **unique `token_hash`**; index (`household_id`, `status`); FK `households` restrict; FK `users` (`invited_by`) restrict |

Migrations: `Household/database/migrations/2026_11_21_000001_create_household_invitations_table.php` and `…_000002_create_household_qr_invitations_table.php`, one table each. All uuid columns `uuid(...)->charset('ascii')->collation('ascii_bin')`. No cascade: invitations are never removed and households are only soft-deleted.

The address is stored in clear: the owner's list shows it and the match needs it. The only secret, the QR token, is stored as a hash (K16).

## Actions

| Action | Input → output | Rules | Transaction / lock |
|---|---|---|---|
| `Identity\FindAccountIdByEmail` | `string $email` → `?string` | exact match on the stored (lowercased) email, read-only | — |
| `InviteMember` | `User $inviter`, `Household`, `string $email` → `HouseholdInvitation` | In order: `FindAccountIdByEmail` = inviter → `invitation.self`; = a live member → `invitation.already_member`; pending for this email → `invitation.already_pending`; 10 live members → `household.member_limit`; 10 pending → `invitation.limit_reached`; 20 in 24 h → `request.throttled` (K10). Then insert with `expires_at = now + 7 days`, and queue `InvitationMail` (K7) | `DB::transaction`, household row lock (K3) |
| `ListHouseholdInvitations` | `Household` → cursor page of `HouseholdInvitation` | REQ-2.1: `pending()`, `created_at`, `id` | — |
| `CancelInvitation` | `Household`, `string $invitationId` → void | REQ-2.3, 2.4: conditional update to `cancelled` scoped to the household (K5, K8) | single `UPDATE` |
| `ListMyInvitations` | `User` → `ReceivedInvitationPage` | REQ-3.1–3.4: none when `verifiedEmail()` is `null`; else `pending()` with that email, `created_at`, `id`, eager-loads `household`; inviter names from `FindAccountNames` for the page only | — |
| `AddMember` | `Household`, `User` → void | K18: `FindMembership` → `household.already_joined`; 10 live members → `household.member_limit`; insert the `member` row. Called only inside a caller's transaction, after its locks | caller's |
| `AcceptInvitation` | `User`, `HouseholdInvitation` → `Household` | REQ-4.1–4.4: lock household (gone → `invitation.not_found`), lock invitation and re-check pending and email (else `invitation.not_found`), `AddMember`, mark `accepted` + `closed_at`. Unique violation → `household.already_joined` (K4) | `DB::transaction`, household then invitation lock |
| `CreateQrInvitation` | `User $inviter`, `Household` → `NewQrInvitation` | REQ-9.1–9.4: cancel the household's pending QR rows, 10 members → `household.member_limit`, generate the token, insert its hash with `expires_at = now + 10 min` (K16, K17) | `DB::transaction`, household row lock |
| `PreviewQrInvitation` | `HouseholdQrInvitation` → `QrInvitationPreview` | REQ-10.1: household (eager-loaded) and inviter name from `FindAccountNames`; read-only | — |
| `AcceptQrInvitation` | `User`, `HouseholdQrInvitation` → `Household` | REQ-10.2, 10.3: lock household (gone → `invitation.not_found`), lock the QR row and re-check pending (else `invitation.not_found`), `AddMember`, mark `accepted` + `closed_at`. Unique violation → `household.already_joined` | `DB::transaction`, household then QR row lock |
| `DeclineInvitation` | `HouseholdInvitation` → void | REQ-4.5, 4.6: conditional update to `declined` (K5) | single `UPDATE` |
| `RemoveMember` | `Household`, `string $userId` → void | REQ-5.1–5.3: malformed id or no live membership in this household → `member.not_found`; owner → `household.owner_cannot_leave`; conditional soft delete (K12) | — |
| `LeaveHousehold` | `User`, `Household` → void | REQ-6.1, 6.2: owner → `household.owner_cannot_leave`; conditional soft delete of the caller's membership (K12) | — |
| `DeleteHousehold` (changed) | unchanged | adds REQ-7.1 (K11) | `DB::transaction`, household row lock |

No new events: nothing outside `Household` needs to know about invitations, and `Ledger` reads membership live (REQ-6.3).

## Endpoints

All under `/api/v1`, `auth:sanctum`. Full bodies in [contract.md](./contract.md).

| Method | Path | Middleware | Request | Response | Errors (in order) |
|---|---|---|---|---|---|
| POST | `households/{household}/invitations` | `RequireHousehold` | `InviteMemberRequest` (authorize `manageMembers`) | 201 `InvitationResource` | 401, 404, 403, 422, 429 |
| GET | `households/{household}/invitations` | `RequireHousehold` | — (authorize `manageMembers`) | `InvitationList` | 401, 404, 403 |
| DELETE | `households/{household}/invitations/{invitation}` | `RequireHousehold` | — (authorize `manageMembers`) | 204 | 401, 404, 403, 404 |
| GET | `invitations` | — | — | `ReceivedInvitationList` | 401 |
| POST | `invitations/{invitation}/accept` | — | — (authorize `answer`) | 200 `HouseholdResource` (role `member`) | 401, 404, 422 |
| POST | `invitations/{invitation}/decline` | — | — (authorize `answer`) | 204 | 401, 404 |
| DELETE | `households/{household}/members/{user}` | `RequireHousehold` | — (authorize `manageMembers`) | 204 | 401, 404, 403, 404, 422 |
| POST | `households/{household}/leave` | `RequireHousehold` | — | 204 | 401, 404, 422 |
| POST | `households/{household}/qr-invitations` | `throttle:creations`, `RequireHousehold` | — (authorize `manageMembers`) | 201 `NewQrInvitationResource` | 401, 429, 404, 403, 422 |
| POST | `qr-invitations/preview` | `throttle:household-qr` | `QrTokenRequest` (authorize `use`) | 200 `QrInvitationPreviewResource` | 401, 429, 422, 404 |
| POST | `qr-invitations/accept` | `throttle:household-qr` | `QrTokenRequest` (authorize `use`) | 200 `HouseholdResource` (role `member`) | 401, 429, 422, 404, 422 |

`InvitationController::accept` and `decline` load the invitation by raw id (K8, `null` when malformed or unknown), `Gate::authorize('answer', [HouseholdInvitation::class, $invitation])`, then call the action, which re-checks under its lock.

`QrTokenRequest` validates `token` (`required|string`) first. The controller then runs `findByToken`, `Gate::authorize('use', [HouseholdQrInvitation::class, $qrInvitation])` and the action (K20). A missing token is a `422`; a wrong one is a `404`.

Email invitation routes have no extra throttle: spec 001's global API limit applies, and the only email-sending route is limited per household (K10). The QR routes are throttled as in K19.

## Authorization

- `RequireHousehold` (unchanged) gives `404 household.not_found` to anyone who is not a live member.
- `HouseholdPolicy::manageMembers`: `view`, then role `owner`, else `deny(code: 'household.forbidden')`.
- `InvitationPolicy::answer`: K9. `QrInvitationPolicy::use`: K20. Both registered with `Gate::policy` in `HouseholdServiceProvider`.
- `POST leave` needs only `view` (done by the middleware); the owner refusal is a business rule in `LeaveHousehold` (`422`), not a `403`.

## Error codes

New:

- `invitation.not_found` 404: unknown, malformed, foreign, not mine, not pending (expired, cancelled, declined, accepted), household deleted. For QR: unknown token, used, cancelled, expired, household deleted, or the scanner's email not confirmed.
- Field codes `token.required`, `token.string` (QR preview and join).
- `invitation.self` 422, `invitation.already_member` 422, `invitation.already_pending` 422, `invitation.limit_reached` 422.
- `household.member_limit` 422 (invite and accept), `household.already_joined` 422, `household.owner_cannot_leave` 422.
- `member.not_found` 404.

Reused: `household.not_found` 404, `household.forbidden` 403, `request.throttled` 429, `auth.unauthenticated` 401, `validation.failed` 422 with `email.required`, `email.string`, `email.email`, `email.max`.

## Docs

- `CLAUDE.md`: "Current state" names spec 004's routes and says transactions come with spec 005; "Hashing" says email invitations carry no token (they are matched to a verified email), and QR invitation tokens are stored as SHA-256 hashes, single-use, 10 minutes; the module table gets `household_qr_invitations` next to `household_invitations`.
- `README.md`: status line.
- Postman (REQ-8.10): folder `Invitations and members (spec 004)`, 11 requests. `POST …/invitations` and `GET invitations` store `invitation_id`; `POST …/qr-invitations` stores `qr_token`. New environment variables: `invitee_email`, `invitation_id`, `member_id`, `qr_token` (empty).

## Requirement coverage

| REQ | Covered by |
|---|---|
| 0.1–0.2 | `InviteMemberRequest`, `InvitationRules`; `InviteMemberTest` |
| 1.1–1.3 | `InviteMember`, `InvitationMail` (K7); `InviteMemberTest`, `InvitationMailTest` |
| 1.4–1.9, 1.11 | `InviteMember` (K3, K10, K14), `FindAccountIdByEmail`; `InviteMemberTest` |
| 1.10, 2.1, 2.4, 5.4 | `HouseholdPolicy::manageMembers` (K9); the endpoint tests |
| 2.1–2.4 | `ListHouseholdInvitations`, `CancelInvitation` (K5); `HouseholdInvitationsTest`, `CancelInvitationTest` |
| 3.1–3.4 | `ListMyInvitations`, `User::verifiedEmail()`, `FindAccountNames`; `MyInvitationsTest` |
| 4.1–4.7 | `InvitationPolicy`, `AcceptInvitation` (K3, K4, K5), `DeclineInvitation`; `AcceptInvitationTest`, `DeclineInvitationTest` |
| 5.1–5.4 | `RemoveMember` (K8, K12); `RemoveMemberTest` |
| 6.1–6.4 | `LeaveHousehold` (K12), spec 003's `RequireBook`; `LeaveHouseholdTest`, Ledger `HouseholdBookAccessTest` |
| 7.1 | `DeleteHousehold` (K11); `DeleteHouseholdTest` |
| 9.1–9.6 | `CreateQrInvitation` (K3, K16, K17), `HouseholdPolicy::manageMembers`, `throttle:creations`; `CreateQrInvitationTest` |
| 10.1–10.5 | `QrTokenRequest`, `QrInvitationPolicy` (K20), `PreviewQrInvitation`, `AcceptQrInvitation`, `AddMember` (K18), limiter `household-qr` (K19); `QrInvitationPreviewTest`, `AcceptQrInvitationTest` |
| 8.1–8.4 | route group, `RequireHousehold`, policies; 401 and order tests per endpoint |
| 8.5 | K1, K12; `HouseholdDataTest` (rows kept) |
| 8.6 | `NoPersonalDataInLogsTest` (Household, new): invite, accept and a failing mail log no address or name |
| 8.7 | existing `tests/Architecture`, unchanged |
| 8.8 | `UserEmailAndLocaleTest`, `FindAccountIdByEmailTest` |
| 8.9 | every test file above, both modes via `WebBrowser` and `HouseholdScenario::as` |
| 8.10 | **Docs** |

## Risks / alternatives considered

- **Link tokens** (the usual design): rejected by the owner for email invitations (E1). Matching a verified email means a forwarded email grants nothing.
- **A photographed or forwarded QR code** grants one join for at most 10 minutes, to anyone with a confirmed email. The preview step, the single use and the short life keep the window small. If a stranger gets in, the owner sees them in the members list and removes them (REQ-5.1).
- **QR in a shared `household_invitations` table** (a `kind` column): rejected (K15).
- **Unique index for one pending invitation per email**: needs a stored "pending" flag that turns off at expiry, which needs a scheduled job or a clock-free expiry rule. The household lock (K3) gives the same guarantee with no job. It serializes invites and accepts per household, which is cheap at 10 members.
- **Spam through notices**: anyone can create an account and a household and send up to 20 notices a day to any address. The fixed subject and plain-text body limit abuse; requiring a confirmed email from the inviter, or a per-account daily cap, can be added later if the owner wants.
- **Owner learns that an address belongs to a member** (`invitation.already_member`): requested by the owner, and limited to the 10 members of their own household. Addresses of other households are never revealed (REQ-1.3).
- **Notice email fails**: the invitation stays valid; the invitee sees it in the app anyway. The job retries 3 times; failures are logged without the address (the payload is encrypted).
- **Cancelling invitations in a `HouseholdDeleted` listener** instead of in `DeleteHousehold`: would leave a window, and a listener of a module's own event adds nothing. Rejected (K11).
