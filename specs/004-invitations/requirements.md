# 004 — Invitations and members: Requirements

> Status: Approved (2026-11-21), with the QR invitation and readings R1–R10, when the owner asked to implement it
> Scope: `app/Modules/Household` (invitations, removing a member, leaving). In `Identity`, two read-only methods on `User` and one read-only action. A test in `Ledger`. Also `CLAUDE.md`, the README and the Postman collection.

## Context

Spec 003 built households with one member, their owner, and the roles, access rules and member list that sharing needs. This spec lets the owner bring people in and lets people go: the owner invites an email address, the invitee sees the invitation in the app and accepts or declines it, the owner can remove a member and a member can leave.

Email invitations carry no link or token. An email invitation is matched to the invitee's **verified** account email, so only the person who controls that mailbox can answer it. The email we send is only a notice that tells them to sign in or sign up with that address.

For people standing next to the owner, there is also a **QR invitation**. The owner shows a QR code in the app. Someone signed in scans it, sees which household it is, and confirms. The QR code holds a secret token that works once and only for 10 minutes. The server stores only its hash.

Spec 003's decisions D1–D14 stand and are not reopened. The ones this spec leans on:

- **D3**: roles `owner` and `member`.
- **D8**: a record you cannot see answers like one that does not exist.
- **D9**: one household per user.
- **D14**: memberships and households are soft-deleted, never removed.
- **REQ-4.2 of spec 003**: access to a household's book is read from the live membership at each request.

### Owner decisions

Given on 2026-11-21. Not to be reopened:

| # | Topic | Decision |
|---|---|---|
| E1 | No link tokens | The owner invites an email address. The invitee sees pending invitations in the app, matched case-insensitively to their **verified** account email, and accepts or declines there. The email is only a notice ("X invited you to household Y, sign in or sign up with this address"), sent in the inviter's locale, with `fr` and `en` texts in the Household module. Someone without an account signs up first, then sees the invitation. |
| E2 | Who manages invitations | Only the household's owner invites, lists pending invitations and cancels one. |
| E3 | Limits | An invitation expires 7 days after it is sent. One pending invitation per email per household; inviting the same email again while one is pending is refused. At most 10 pending invitations and at most 10 members per household. At most 20 invitations per household per day. |
| E4 | Accepting | Accepting makes the invitee a `member`. Because of D9, accepting is refused while the invitee belongs to a household that is not deleted. |
| E5 | Leaving and removing | The owner can remove a member. A member can leave. The owner cannot leave; they delete the household instead (spec 003). Leaving or being removed ends access to the household's book at once. |
| E6 | Deleted household | Deleting a household cancels its pending invitations. |
| E7 | Roles | Changing roles and transferring ownership stay out of scope. |
| E8 | No leak | An invitation that is not mine, expired, cancelled or already answered answers like one that does not exist: `404 invitation.not_found`. |

Added on 2026-11-21 in the question dialog (QR invitation). E1 still holds for email invitations; these decisions bring a token in for QR invitations only:

| # | Topic | Decision |
|---|---|---|
| E9 | QR invitation | The owner can show a QR code in the app. The scanner, signed in, first sees the household and the inviter (preview), then confirms to join as `member`. Joining follows the same rules as accepting an email invitation: one household per user, at most 10 members. |
| E10 | QR lifetime | A QR code works once and expires 10 minutes after it is created. Creating a new one cancels the household's previous unused one. |
| E11 | Who may scan | Any signed-in account whose email is confirmed. |
| E12 | Where | Part of spec 004, not a separate spec. |

### Readings of the decisions (confirm at approval)

The decisions above leave a few details open. These are the readings this spec uses; each is a one-line change if the owner wants otherwise.

| # | Point | Reading used |
|---|---|---|
| R1 | Does the owner count in the 10 members? | Yes. 10 members means 10 live memberships, owner included. |
| R2 | When is the member limit checked? | At invite time (no new invitation when the household already has 10 members) and again at accept time. Pending invitations are not added to the member count. |
| R3 | What "20 per day" counts | Every invitation sent by the household in the last 24 hours (rolling), whatever happened to it since (cancelled ones count). Refused attempts do not count. Over the limit: `429 request.throttled` with `retry_after_seconds`, like every other limit. |
| R4 | Inviting someone who is in another household | Allowed, with the same answer as for anyone else, so the owner learns nothing about other households. They can only accept after leaving it (E4). |
| R5 | Removing the owner through `DELETE members/{owner}` | Refused with `422 household.owner_cannot_leave`, the same rule as leaving. |
| R6 | Answers | Accepting answers `200` with the household (role `member`), so the app can open it. Declining, cancelling, removing and leaving answer `204`. |
| R7 | Account with an unconfirmed email | Sees no invitation and cannot answer one (`404`). |
| R8 | QR invitations and the email limits | A QR invitation sends no email, so it does not count in the 10 pending invitations or the 20 per day. Creating one uses the shared `creations` limit of spec 003 (10 per minute per account). |
| R9 | Scanning attempts | Preview and join by QR are limited to 10 per minute per account, then `429 request.throttled`. |
| R10 | Ending a QR invitation early | No separate cancel route. It expires after 10 minutes, and showing a new one cancels it. |

## User stories

- **US-1**: As a household owner, I want to invite someone by email address, so that they can share the household's book.
- **US-2**: As a household owner, I want to see and cancel the invitations still waiting for an answer, so that I can correct a mistake.
- **US-3**: As an invited person, I want to see the invitations sent to my email address once I am signed in, so that I know who invited me where.
- **US-4**: As an invited person, I want to accept or decline an invitation, so that I join the household or make the invitation go away.
- **US-5**: As a household owner, I want to remove a member, so that they no longer see the household's money.
- **US-6**: As a member, I want to leave a household, so that I am free to join or create another.
- **US-7**: As the owner of the app, I want invitations to a deleted household to stop working.
- **US-8**: As the owner of the app, I want invitations to reveal nothing about other accounts and households, and the module rules kept.
- **US-9**: As a household owner, I want to show a QR code, so that someone next to me can join without typing an email address.
- **US-10**: As someone scanning that QR code, I want to see which household it is before I join, so that I don't join the wrong one by mistake.

## Acceptance criteria (EARS)

Error codes are API codes. Field codes follow `<field>.<rule>` (spec 001, REQ-6.3). "Household" means the shape of spec 003 REQ-2.4.

An invitation is **pending** while it has been neither accepted, declined nor cancelled, it has not expired, and its household is not deleted. Only pending invitations can be listed, answered or cancelled.

### Shared field rules

- **REQ-0.1** `email`: trimmed and lowercased before any check. IF missing or blank THEN `email.required`; IF not a string THEN `email.string`; IF not a valid address THEN `email.email`; IF longer than 255 characters THEN `email.max`.
- **REQ-0.2** Every validation failure SHALL answer `422 validation.failed` with a code per field, and change nothing.

### US-1: Invite

- **REQ-1.1** WHEN the owner of a household posts `email` to `POST /api/v1/households/{household}/invitations` THE SYSTEM SHALL record a pending invitation for that address, expiring 7 days later, and answer `201` with the invitation (REQ-2.2).
- **REQ-1.2** WHEN an invitation is recorded THE SYSTEM SHALL queue one notice email to the invited address, after the invitation is saved.
  - It names the inviter (their account name) and the household, and says to sign in or sign up with this address to see the invitation, and when it expires.
  - It is written in the inviter's locale (`fr` or `en`), with both texts in the Household module.
  - It carries no link token and no code. It is encrypted on the queue, like the codes of spec 002.
- **REQ-1.3** The answer and the email SHALL be the same whether or not an account exists for the address, and whether or not that account belongs to another household (R4).
- **REQ-1.4** IF the address is the owner's own account email THEN `422 invitation.self`.
- **REQ-1.5** IF the address is the email of a live member of this household THEN `422 invitation.already_member`.
- **REQ-1.6** IF a pending invitation already exists for the same address in this household (any letter case) THEN `422 invitation.already_pending`. An expired, cancelled, declined or accepted invitation does not block a new one. Concurrent requests SHALL NOT create two pending invitations for the same address in a household.
- **REQ-1.7** IF the household already has 10 live members, owner included, THEN `422 household.member_limit` (R1, R2).
- **REQ-1.8** IF the household already has 10 pending invitations THEN `422 invitation.limit_reached`. Concurrent requests SHALL NOT take a household past 10.
- **REQ-1.9** IF the household has sent 20 invitations in the last 24 hours THEN `429 request.throttled` with `retry_after_seconds`: the seconds until the oldest of those 20 is 24 hours old (R3). Concurrent requests SHALL NOT take a household past 20.
- **REQ-1.10** IF the caller is a `member` of the household THEN `403 household.forbidden`, whatever the body.
- **REQ-1.11** When several refusals apply, the first in this order wins: REQ-1.10, REQ-0.1, REQ-1.4, REQ-1.5, REQ-1.6, REQ-1.7, REQ-1.8, REQ-1.9. A refused request records nothing and sends no email.

### US-2: Owner's pending invitations

- **REQ-2.1** WHEN the owner calls `GET /api/v1/households/{household}/invitations` THE SYSTEM SHALL answer `200` with the household's pending invitations only, oldest first, cursor pagination. A `member` gets `403 household.forbidden`.
- **REQ-2.2** An invitation, as the owner sees it, SHALL have `id`, `email` (as stored, lowercased), `created_at` and `expires_at` (ISO 8601, UTC).
- **REQ-2.3** WHEN the owner calls `DELETE /api/v1/households/{household}/invitations/{invitation}` on a pending invitation of that household THE SYSTEM SHALL mark it cancelled and answer `204`. No email is sent.
- **REQ-2.4** IF the invitation does not exist, the id is malformed, it belongs to another household, or it is not pending THEN `404 invitation.not_found`, the same answer in every case. A `member` gets `403 household.forbidden` first.

### US-3: My invitations

- **REQ-3.1** WHEN a signed-in user calls `GET /api/v1/invitations` THE SYSTEM SHALL answer `200` with the pending invitations whose address equals the user's verified account email, ignoring letter case. Oldest first, cursor pagination. A user with none gets an empty list.
- **REQ-3.2** IF the user's email is not confirmed THEN the list SHALL be empty (R7).
- **REQ-3.3** Each invitation, as the invitee sees it, SHALL have `id`, `household` (`id`, `name`), `invited_by` (`name` only, current account name), `created_at` and `expires_at`. It SHALL NOT show the invited address, the inviter's email, or any other member.
- **REQ-3.4** The list SHALL still show an invitation while the user belongs to a household; accepting it is what is refused (REQ-4.3).

### US-4: Accept or decline

- **REQ-4.1** WHEN a user calls `POST /api/v1/invitations/{invitation}/accept` on a pending invitation sent to their verified email THE SYSTEM SHALL, in one transaction, add them to the household as `member` and mark the invitation accepted, and answer `200` with the household (role `member`, R6).
- **REQ-4.2** IF the invitation does not exist, the id is malformed, it was sent to another address, the user's email is not confirmed, it has expired, been cancelled, declined or accepted, or its household is deleted THEN `404 invitation.not_found`. Status, body and headers SHALL be the same in every case, apart from `X-Request-Id` (E8).
- **REQ-4.3** IF the user belongs to a household that is not deleted, in any role, THEN `422 household.already_joined`, and the invitation stays pending. Concurrent accepts SHALL NOT give a user two households (spec 003, K2).
- **REQ-4.4** IF the household already has 10 live members THEN `422 household.member_limit`, and the invitation stays pending. Concurrent accepts SHALL NOT take a household past 10.
- **REQ-4.5** WHEN a user calls `POST /api/v1/invitations/{invitation}/decline` on a pending invitation sent to their verified email THE SYSTEM SHALL mark it declined and answer `204`. Otherwise `404 invitation.not_found` as in REQ-4.2. Declining works whether or not the user belongs to a household.
- **REQ-4.6** An invitation SHALL be answered at most once: once accepted, declined or cancelled, every later accept, decline or cancel gives `404 invitation.not_found`, also when two of them race.
- **REQ-4.7** After accepting, the user SHALL see the household in `GET /households`, appear in its members list, and reach the household's book (spec 003, REQ-4.2). Their other pending invitations stay listed; accepting one of them gives `422 household.already_joined`.

### US-5: Remove a member

- **REQ-5.1** WHEN the owner calls `DELETE /api/v1/households/{household}/members/{user}` with the account id of a live `member` of that household THE SYSTEM SHALL soft-delete that membership and answer `204`.
- **REQ-5.2** IF `{user}` is unknown, malformed, not a live member of this household, or already removed THEN `404 member.not_found`, the same answer in every case.
- **REQ-5.3** IF `{user}` is the owner THEN `422 household.owner_cannot_leave` (R5).
- **REQ-5.4** IF the caller is a `member` THEN `403 household.forbidden`, whatever `{user}` is.

### US-6: Leave

- **REQ-6.1** WHEN a `member` calls `POST /api/v1/households/{household}/leave` THE SYSTEM SHALL soft-delete their membership and answer `204`.
- **REQ-6.2** IF the caller is the owner THEN `422 household.owner_cannot_leave`, and nothing changes. The owner deletes the household instead (spec 003, REQ-2.9).
- **REQ-6.3** WHEN a membership ends (removed or left) THE SYSTEM SHALL end that user's access at once: the household and its book answer `404` to them and are left out of `GET /households` and `GET /books` from the next request on.
- **REQ-6.4** A user whose membership ended SHALL be free to create a household, accept another invitation, or be invited to the same household again. Their past invitations stay answered.

### US-7: Deleted household

- **REQ-7.1** WHEN a household is deleted (spec 003, REQ-2.9) THE SYSTEM SHALL mark its pending invitations, email and QR, cancelled in the same transaction. They leave every list and answer `404 invitation.not_found`.

### US-8: Access, privacy, modules

- **REQ-8.1** Every route of this spec SHALL require a signed-in user, by web session or bearer token. Without either: `401 auth.unauthenticated`, and no other check runs.
- **REQ-8.2** Routes under `/households/{household}` SHALL pass through `RequireHousehold` (spec 003): an unknown, malformed or foreign household gives `404 household.not_found`.
- **REQ-8.3** Checks SHALL run in this order: `401`, then `404 household.not_found`, then `403`, then `404` for the record in the path (`invitation.not_found`, `member.not_found`), then `422`, then `429`. On routes with a throttle middleware (REQ-9.1, REQ-10.4) the `429` of that throttle comes right after `401`.
- **REQ-8.4** Every route SHALL be authorized by a policy: the owner-only routes by the household policy, and accept, decline, QR preview and QR join by invitation policies that deny with `invitation.not_found`.
- **REQ-8.5** Invitation rows, email and QR, SHALL never be removed; their status records what happened. Removing a member or leaving SHALL soft-delete the membership (spec 003, D14).
- **REQ-8.6** Logs SHALL hold no email address, account or household name, QR token or token hash, including when the notice email fails.
- **REQ-8.7** `Identity` SHALL NOT use any class of `Household`; `Household` SHALL NOT use any class of `Ledger`. The existing architecture tests SHALL pass unchanged.
- **REQ-8.8** In `Identity`, this spec SHALL add only: a `User` method giving the verified email (or none), a `User` method giving the locale code, and a read-only action giving the account id for an email. `Household` never queries `users`.
- **REQ-8.9** Every endpoint SHALL have feature tests for success, validation errors (where it takes input), `401`, another household's or another user's record, and the expired invitation where one applies, at least once in cookie mode and once in token mode. Every rule SHALL have a test whose name starts with its `REQ` id.
- **REQ-8.10** The Postman collection SHALL get one request per new route in an `Invitations and members (spec 004)` folder; the test scripts store `invitation_id` and `qr_token`. `CLAUDE.md` and the README SHALL be updated.

### US-9: Show a QR invitation

- **REQ-9.1** WHEN the owner calls `POST /api/v1/households/{household}/qr-invitations` THE SYSTEM SHALL create a QR invitation and answer `201` with its `id`, its `token` and its `expires_at`, 10 minutes later (E10). The route uses the shared `creations` limit (R8).
- **REQ-9.2** The `token` SHALL be random, at least 256 bits, URL-safe, and returned only in that answer. THE SYSTEM SHALL store only its SHA-256 hash, and no route SHALL return it again. What the QR code encodes around the token (for example a link that opens the app) is left to the frontend spec.
- **REQ-9.3** WHEN a QR invitation is created THE SYSTEM SHALL cancel the household's previous QR invitation that is still pending, in the same transaction. A household SHALL have at most one pending QR invitation, also when two requests race.
- **REQ-9.4** IF the household already has 10 live members THEN `422 household.member_limit`, and nothing changes.
- **REQ-9.5** IF the caller is a `member` THEN `403 household.forbidden`.
- **REQ-9.6** A QR invitation SHALL NOT appear in either list of email invitations (REQ-2.1, REQ-3.1) and SHALL NOT count in their limits (REQ-1.8, REQ-1.9).

### US-10: Scan a QR invitation

A QR invitation is **pending** while it has been neither used nor cancelled, its 10 minutes have not passed, and its household is not deleted.

- **REQ-10.1** WHEN a signed-in user with a confirmed email posts `token` to `POST /api/v1/qr-invitations/preview` and the token belongs to a pending QR invitation THE SYSTEM SHALL answer `200` with `household` (`id`, `name`), `invited_by` (`name`) and `expires_at`, and change nothing.
- **REQ-10.2** WHEN such a user posts `token` to `POST /api/v1/qr-invitations/accept` THE SYSTEM SHALL, in one transaction, add them to the household as `member` and mark the QR invitation used, and answer `200` with the household (role `member`). The rules of REQ-4.3 (`household.already_joined`) and REQ-4.4 (`household.member_limit`) apply, and on those refusals the QR invitation stays pending.
- **REQ-10.3** IF the token is unknown, has been used, was cancelled, has expired, or its household is deleted, or the user's email is not confirmed, THEN `404 invitation.not_found`. Status, body and headers SHALL be the same in every case, apart from `X-Request-Id`. A QR invitation SHALL be used at most once, also when two scanners race.
- **REQ-10.4** Preview and join SHALL be limited together to 10 requests per minute per account, then `429 request.throttled` with `retry_after_seconds` (R9).
- **REQ-10.5** `token`: IF missing or blank THEN `token.required`; IF not a string THEN `token.string`. The token travels only in the request body, never in a URL.

Checklist:
- [x] Validation errors (per field, with stable codes): REQ-0.1, REQ-0.2, REQ-10.5
- [x] Unauthenticated access (401), cookie and token modes: REQ-8.1, REQ-8.9
- [x] Access to another household's data: REQ-2.4, REQ-4.2, REQ-5.2, REQ-8.2, REQ-8.3, REQ-10.3
- [x] Rate limits / repeated requests / duplicates: REQ-1.6, REQ-1.8, REQ-1.9, REQ-4.3, REQ-4.4, REQ-4.6, REQ-9.3, REQ-10.3, REQ-10.4
- [x] Money edge cases: no amounts in this spec
- [x] Date & timezone edge cases: 7-day and 10-minute expiry and the rolling 24 hours, in UTC (REQ-1.1, REQ-1.9, REQ-9.1); expired invitations (REQ-2.4, REQ-4.2, REQ-10.3)
- [x] Deleting / changing referenced data: deleted household (REQ-4.2, REQ-7.1, REQ-10.3), removed or departed member (REQ-6.3, REQ-6.4)

## Non-goals

- Changing roles and transferring ownership (E7).
- Restoring a deleted household or a removed membership.
- Resending the notice email: the owner cancels and invites again.
- Links, tokens or codes in email invitations (E1).
- A QR code that several people can use, or one that lasts longer than 10 minutes (E10). A route to cancel a QR invitation early (R10).
- Drawing the QR code and choosing what it encodes around the token: the frontend spec.
- Telling the owner whether an invitation was declined, or notifying anyone when a member joins, leaves or is removed.
- Changing an account's email address.
- The frontend.

## Open questions

None blocking. The readings R1–R10 above are confirmed or changed at approval.
