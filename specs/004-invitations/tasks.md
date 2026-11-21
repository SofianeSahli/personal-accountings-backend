# 004 — Invitations and members: Tasks

> Status: Done (2026-11-21). Approved by the owner on 2026-11-21 ("implement push and make a PR"); deviations below await review in the PR.
> Design: [design.md](./design.md) · Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

Order: Identity reads → tables and models → mail → actions with their endpoints → cross-cutting checks → docs.
Each task is about 100 changed lines. Each task: its tests first (named `REQ-x.y …`), then the code; `php artisan test`, Pint and Larastan pass. After each task, stop and give the owner a plain-words summary, file by file.

- [x] **T1** — Identity: verified email, locale code, account by email
  - Fulfils: REQ-8.8 (K6)
  - Files: `Identity/Models/User` (`verifiedEmail()`, `localeCode()`), `Identity/Actions/Accounts/FindAccountIdByEmail`
  - Verify: `UserEmailAndLocaleTest` (unconfirmed gives `null`, mixed case lowercased, unset locale gives `fr`), `FindAccountIdByEmailTest` (found, unknown, empty string)

- [x] **T2** — Invitations table, model and factory
  - Fulfils: REQ-8.5, data side of REQ-1.1 and REQ-1.6 (K1, K2, K14)
  - Files: migration `2026_11_21_000001_create_household_invitations_table.php`, `HouseholdInvitation` (constants, `household()`, `scopePending`), `InvitationStatus`, `Household::MEMBER_LIMIT`, `HouseholdInvitationFactory` (states `expired`, `accepted`, `declined`, `cancelled`), `HouseholdScenario::invite()`
  - Verify: `migrate:fresh`; `InvitationDataTest` (the `pending` scope leaves out expired, closed and deleted-household rows; `email` compares accent-sensitively)

- [x] **T3** — Notice email
  - Fulfils: REQ-1.2 (K7)
  - Files: `Household/Mail/InvitationMail`, `resources/lang/{fr,en}/mail.php`, `resources/views/mail/invitation.blade.php`, `HouseholdServiceProvider` (views and translations)
  - Verify: `InvitationMailTest` (queued and encrypted, sent after commit only, `fr` and `en` texts with inviter and household names, no token or code in the body)

- [x] **T4** — `InviteMember` action
  - Fulfils: REQ-1.1–1.9, REQ-1.11 (K3, K10)
  - Files: `Actions/InviteMember`
  - Verify: `InviteMemberRuleTest` (self, already member, already pending in another letter case, expired or closed invitation does not block, member in another household is invited normally, 10 members, 10 pending, 20 in 24 h with `retry_after_seconds` via `travelTo`, refusal order, nothing recorded or mailed on refusal)

- [x] **T5** — Invite endpoint
  - Fulfils: REQ-0.1, REQ-0.2, REQ-1.1, REQ-1.3, REQ-1.10, REQ-8.1–8.4 (K9)
  - Files: `HouseholdPolicy::manageMembers`, `InviteMemberRequest`, `InvitationRules`, `InvitationResource`, `HouseholdInvitationController::store`, `routes.php`
  - Verify: `InviteMemberTest` (201 in cookie and token modes, same answer with and without an account, field codes, 401, 404 foreign household, 403 member before 422)

- [x] **T6** — Owner's list and cancel
  - Fulfils: REQ-2.1–2.4 (K5, K8)
  - Files: `ListHouseholdInvitations`, `CancelInvitation`, `InvitationList`, `HouseholdInvitationController::{index,destroy}`, `routes.php`
  - Verify: `HouseholdInvitationsTest` (pending only, order, cursor, 403, 404, 401), `CancelInvitationTest` (204 and gone from both lists; unknown, malformed, other household's, expired, answered all give the same `404`; 403 first)

- [x] **T7** — My invitations
  - Fulfils: REQ-3.1–3.4 (K2)
  - Files: `ListMyInvitations`, `Support/ReceivedInvitationPage`, `ReceivedInvitationResource`, `ReceivedInvitationList`, `InvitationController::index`, `routes.php`
  - Verify: `MyInvitationsTest` (case-insensitive match, unconfirmed email sees none, other addresses, expired, answered and deleted-household invitations left out, shown while in a household, current inviter name, no email in the body, 401, both modes)

- [x] **T8** — Accept, and the shared join rule
  - Fulfils: REQ-4.1–4.4, REQ-4.6, REQ-4.7 (K4, K5, K9, K18)
  - Files: `AddMember`, `InvitationPolicy`, `AcceptInvitation`, `InvitationController::accept`, `HouseholdServiceProvider` (policy), `routes.php`
  - Verify: `AcceptInvitationTest` (200 with role `member` in both modes, then in `GET households` and the members list; `404` byte-equal for unknown, malformed, another address, unconfirmed email, expired, cancelled, declined, accepted, deleted household; `already_joined` as owner and as member elsewhere, invitation still pending; a deleted former household does not block; `member_limit`; unique index violation mapped; 401)

- [x] **T9** — Decline
  - Fulfils: REQ-4.5, REQ-4.6
  - Files: `DeclineInvitation`, `InvitationController::decline`, `routes.php`
  - Verify: `DeclineInvitationTest` (204 in both modes, gone from both lists, works while in a household, every `404` case, accept after decline gives `404`, 401)

- [x] **T10** — QR invitations table, model and factory
  - Fulfils: REQ-8.5, REQ-9.2 (data side), REQ-9.6 (K14, K15, K16)
  - Files: migration `2026_11_21_000002_create_household_qr_invitations_table.php`, `HouseholdQrInvitation` (`VALID_MINUTES`, `household()`, `scopePending`, `findByToken`, token generation and hashing), `HouseholdQrInvitationFactory` (states `expired`, `accepted`, `cancelled`; a known token for tests), `HouseholdScenario::qrInvitation()`
  - Verify: `migrate:fresh`; `QrInvitationDataTest` (duplicate `token_hash` refused; `findByToken` finds by the plain token and never stores it; the `pending` scope leaves out expired, closed and deleted-household rows)

- [x] **T11** — Show a QR invitation
  - Fulfils: REQ-9.1–9.6 (K3, K16, K17, K19)
  - Files: `CreateQrInvitation`, `Support/NewQrInvitation`, `NewQrInvitationResource`, `HouseholdQrInvitationController::store`, `routes.php`
  - Verify: `CreateQrInvitationTest` (201 in both modes, 43-character token, `expires_at` 10 minutes later, only its hash in the table, a second one cancels the first, `member_limit`, 403 member, 404 foreign household, 401, shared `creations` limit gives 429, not listed in either email invitation list)

- [x] **T12** — Preview and join by QR
  - Fulfils: REQ-10.1–10.5 (K18, K19, K20)
  - Files: `QrInvitationPolicy`, `PreviewQrInvitation`, `AcceptQrInvitation`, `Support/QrInvitationPreview`, `QrTokenRequest`, `QrInvitationPreviewResource`, `QrInvitationController::{preview,accept}`, limiter `household-qr` and policy in `HouseholdServiceProvider`, `routes.php`
  - Verify: `QrInvitationPreviewTest` (200 in both modes, nothing changed, field codes, `404` byte-equal for unknown, used, replaced, expired, deleted household, unconfirmed email; 401), `AcceptQrInvitationTest` (200 with role `member`, the code then gives `404`; `already_joined` and `member_limit` leave it pending; the 11th request in a minute gives 429 across both routes; 401)

- [x] **T13** — Remove a member
  - Fulfils: REQ-5.1–5.4, REQ-6.3, REQ-6.4 (K12)
  - Files: `RemoveMember`, `HouseholdMemberController::destroy`, `routes.php`
  - Verify: `RemoveMemberTest` (204 in both modes, row kept with `deleted_at`, the removed user then gets `404` on the household and can create one or be invited again; unknown, malformed, other household's, already removed give `member.not_found`; owner gives `owner_cannot_leave`; 403 member first; 401)

- [x] **T14** — Leave, and book access after a membership ends
  - Fulfils: REQ-6.1–6.4
  - Files: `LeaveHousehold`, `HouseholdMemberController::leave`, `routes.php`, `Ledger/Tests/HouseholdBookAccessTest`
  - Verify: `LeaveHouseholdTest` (204 in both modes, owner `422`, nothing changed for the owner, then free to accept another invitation, 401, 404 foreign household); `HouseholdBookAccessTest` (after leaving and after removal, the book answers `404` and leaves `GET books` on the next request)

- [x] **T15** — Deleting a household cancels its invitations
  - Fulfils: REQ-7.1 (K11)
  - Files: `DeleteHousehold` (household row lock, cancel pending email and QR invitations)
  - Verify: `DeleteHouseholdTest` additions (pending email and QR invitations become `cancelled` with `closed_at`, answered ones untouched, invitee gets `404` and an empty list, the QR token gives `404`)

- [x] **T16** — Cross-cutting checks
  - Fulfils: REQ-8.6, REQ-8.7, REQ-8.9
  - Files: `Household/Tests/NoPersonalDataInLogsTest` (new), arch tests unchanged
  - Verify: invite, accept, QR create and join, remove and a failing mail job log no address, name, token or token hash; `tests/Architecture` passes; `route:list --path=api/v1` shows the 11 routes of `contract.md`

- [x] **T17** — Docs and Postman
  - Fulfils: REQ-8.10
  - Files: `CLAUDE.md`, `README.md`, `postman/household-expenses.postman_collection.json`, `postman/local.postman_environment.json`
  - Verify: review; both files are valid JSON; 11 requests in `Invitations and members (spec 004)`, each with description, auth, throttle and error codes; `qr_token` stored by the create request's test script

## Deviations from spec
(Record any change made during implementation and when it was approved.)

Made during implementation on 2026-11-21, to be approved in the PR review:

- **D1** — Data tests live in `InvitationDataTest` and `QrInvitationDataTest` instead of new cases in `HouseholdDataTest`.
- **D2** — `User::localeCode()` reads the column directly: `users.locale` is `NOT NULL` with default `fr`, so the "unset locale" fallback and its test are dropped.
- **D3** — `FindAccountIdByEmail` compares the email again in PHP after the query, because `users.email` uses MySQL's accent-insensitive default collation (`andre@` would match `andré@`).
- **D4** — The `email` and `token` rules start with `bail`, so a non-string gives only `.string`, one code per field (as spec 003, D5).
- **D5** — Small model helpers: `Household::lockLive()` and `memberCount()`, `HouseholdMember::endMembership()` (conditional soft delete), `HouseholdInvitation::findByRawId()` (no query for a malformed id). Tests use `HouseholdScenario::invite()`, `qrInvitation()`, `ownerIdOf()` and `refusalOf()`.
- **D6** — `POST households/{household}/qr-invitations` sits outside the `RequireHousehold` group with `throttle:creations` and `RequireHousehold` listed in that order, so the `429` comes before the `404` as REQ-8.3 says.
- **D7** — `DeleteHousehold` does nothing when the locked read finds the household already deleted by a concurrent request (the first request already answered `204`).
- **D8** — The unique-index fallback of REQ-4.3 is tested with a live membership in a soft-deleted household: `FindMembership` misses it and the index refuses it. `FindMembership` is final and cannot be faked (as spec 003, D9).
- **D9** — Races (two invites, two accepts, accept against cancel) are guaranteed by the household and invitation row locks and the conditional updates, but no test runs two requests at once.
- **D10** — The Postman folder is split in three subfolders (`Email invitations`, `QR invitations`, `Members`). "Accept an invitation" and "Join with a scanned QR code" also store `household_id`.

## Verification report
| REQ | Verified how | Result |
|---|---|---|
| 0.1–0.2 | `InviteMemberTest` (5 field cases, nothing recorded or mailed) | Pass |
| 1.1–1.9, 1.11 | `InviteMemberRuleTest` (16 cases incl. the rolling 24 h with `retry_after_seconds` and `Retry-After`), `InviteMemberTest` | Pass |
| 1.2 | `InvitationMailTest` (queued, encrypted, after commit, `fr`/`en`, no link, token or code) | Pass |
| 1.10, 2.1, 2.4, 5.4, 9.5 | 403 tests per owner route, before validation or the path record | Pass |
| 2.1–2.4 | `HouseholdInvitationsTest`, `CancelInvitationTest` | Pass |
| 3.1–3.4 | `MyInvitationsTest` | Pass |
| 4.1–4.7 | `AcceptInvitationTest` (8 refusal cases with byte-equal bodies), `DeclineInvitationTest` | Pass |
| 5.1–5.4 | `RemoveMemberTest` | Pass |
| 6.1–6.4 | `LeaveHouseholdTest`, `RemoveMemberTest`, Ledger `HouseholdBookAccessTest` | Pass |
| 7.1 | `DeleteHouseholdTest` (email and QR invitations cancelled, answered ones untouched) | Pass |
| 8.1–8.4 | 401 test per endpoint; 404 for a foreign household per route; order tests | Pass |
| 8.5 | `InvitationDataTest`, `QrInvitationDataTest`, rows kept with `deleted_at` in remove and leave tests | Pass |
| 8.6 | Household `NoPersonalDataInLogsTest` (every new route, and a real SMTP failure on the sync queue) | Pass |
| 8.7 | `tests/Architecture` unchanged | Pass |
| 8.8 | `UserEmailAndLocaleTest`, `FindAccountIdByEmailTest` | Pass |
| 8.9 | Both modes per endpoint (`WebBrowser`), test names start with their REQ | Pass |
| 8.10 | Postman folder `Invitations and members (spec 004)`, 11 requests; environment gains `invitee_email`, `invitation_id`, `member_id`, `qr_token`; CLAUDE.md and README updated | Review |
| 9.1–9.6 | `CreateQrInvitationTest`, `QrInvitationDataTest` | Pass |
| 10.1–10.5 | `QrInvitationPreviewTest`, `AcceptQrInvitationTest` (incl. the shared 10/min limit) | Pass |
| Routes | `route:list --path=api/v1`: the 11 routes of `contract.md` | Pass |
| Gate | `php artisan test` 569 passed; Pint clean; Larastan level 6 no errors; `composer audit` clean | Pass |
