# 003 — Books and households: Requirements

> Status: Approved (2026-11-20; rewritten that day: no automatic household, money belongs to a book)
> Scope: new modules `app/Modules/Household` and `app/Modules/Ledger` (the `books` table only). In `Identity`, one read-only action for account names. Also `tests/Architecture`, `CLAUDE.md`, the Postman collection and the README.

## Context

Money data belongs to a **book**. A book is owned either by one account (a personal book) or by a household (the household's book). Specs 004–005 (invitations, categories, transactions) attach their records to a book, so a single user can use the app freely and a household is only needed for sharing.

This spec adds:

- the `Household` module: creating a household on demand, reading it, renaming it, listing its members;
- the `Ledger` module with its first table, `books`: creating personal books and the household's book, listing, reading and updating them, and the access rule every later money route uses (`RequireBook`, `CurrentBook`).

Dependencies stay one way: `Ledger` → `Household` → `Identity`. `Identity` never knows about households or books, and nothing is created at sign-up. In this spec a household has one member, its owner. Members join from spec 004 on, but the roles, the access rules and the members list are built now.

### Owner decisions

Given on 2026-11-19 and revised on 2026-11-20. Not to be reopened:

| # | Topic | Decision |
|---|---|---|
| D1 | No automatic creation | Nothing is created when an account is created. `Identity` gets no `AccountCreated` event. The user creates a personal book, or a household and then its book, when they want one. |
| D2 | Book | The money abstraction is a **book**, owned by exactly one account or exactly one household. Categories, transactions and summaries (specs 005+) carry `book_id`. Book lives in `Ledger`. |
| D3 | Roles | Household roles: `owner` and `member`. The household owner renames the household and creates and manages its book; a member only reads. A personal book is managed only by its account. Later specs add invite, remove, delete, leave and the money operations. |
| D4 | Currency | On the book, not the household. ISO 4217, chosen at creation, default `TND`, validated by `App\Shared\Money\Currency`. It cannot be changed. |
| D5 | Timezone | On the book. IANA name, default `Africa/Tunis`. Whoever manages the book can change it. |
| D6 | Visibility | No private entries: every member of a household sees everything in the household's book. |
| D7 | `/me` | Unchanged. The app uses `GET /api/v1/households` and `GET /api/v1/books`. |
| D8 | Not found | A caller with no access gets exactly the same answer as for a record that does not exist: `404 household.not_found` / `404 book.not_found`. |
| D9 | One household per user | A user belongs to at most one household, in any role. |
| D10 | One book per household | A household has at most one book, created by its owner with `POST /api/v1/books` and a `household_id` (Household may not depend on Ledger, so the app makes two calls). |
| D11 | Personal books | Created on demand, several allowed, at most 5 per user. The household's book does not count. |
| D12 | Book name | Every book has its own name, personal or household. The app may pre-fill it with the household's name. |
| D13 | Categories (detailed in spec 005) | Categories are a global list seeded in the database, shared by every book; the user only picks one when recording an entry. Two levels: parent categories, each with child categories. Every parent has an "Other" child, and an "Other" parent exists too. When the user picks an "Other" category they type a name, and that typed name is saved. This replaces the CLAUDE.md rule that default categories are created per household by an action. |
| D14 | Book owner and deleting (2026-11-20) | A book is always linked to its owner: a polymorphic owner (account or household) that can never be empty. Books and households are soft-deleted, never removed, so a book's owner always exists. This spec adds deleting a household (owner only), which also frees its members and soft-deletes its book. |

Answered in the question dialog (2026-11-19, still valid):

| # | Question | Answer |
|---|---|---|
| A1 | Where do member names come from, since `Household` may not query users? | `Identity` adds one read-only action that returns account names by id. `Household` calls it. This is the only change in `Identity`. |
| A3 | Rate limit on creation | 10 creations per minute per user (households and books together). |
| A5 | Household and book names | Trimmed, 1 to 60 characters, need not be unique. |
| A6 | `currency` sent to `PATCH` | Rejected with `currency.prohibited`. |
| A7 | Currency in responses | Code and exponent: `{"code": "TND", "exponent": 3}`. |

## User stories

- **US-1**: As a single user, I want to create a personal book with its currency and timezone, so that I can record my money without a household.
- **US-2**: As a user, I want to create a household when I want to share, and then its book, so that its members record money together.
- **US-3**: As a user, I want to list the books I can use, personal and household, with my rights in each, so that the app knows where to take me.
- **US-4**: As a user, I want to see a book's details, so that amounts and dates are shown in its currency and timezone.
- **US-5**: As the manager of a book, I want to rename it and change its timezone. Household members must not be able to.
- **US-6**: As a user, I want to see my household, and rename or delete it if I own it, so that its members are free to start again elsewhere.
- **US-7**: As a member, I want to see who belongs to the household and their roles.
- **US-8**: As the owner of the app, I want households and books reachable only by those allowed, and ids never to reveal which exist.
- **US-9**: As the owner of the app, I want the module rules (one-way dependencies, no shared tables) kept and checked automatically.

## Acceptance criteria (EARS)

Error codes are API codes. Field codes follow `<field>.<rule>` (spec 001, REQ-6.3). "Household" means the shape in REQ-2.4; "book" means the shape in REQ-3.2.

### Shared field rules

- **REQ-0.1** `name` (household and book): trimmed. IF missing or blank THEN `name.required`; IF not a string THEN `name.string`; IF longer than 60 characters THEN `name.max`. Names need not be unique.
- **REQ-0.2** `currency`: accepted in any letter case, stored in upper case. IF not a string THEN `currency.string`; IF `App\Shared\Money\Currency` does not know it THEN `currency.supported_currency` (named rule `SupportedCurrency`, CLAUDE.md "Errors").
- **REQ-0.3** `timezone`: IF not a string THEN `timezone.string`; IF not a canonical IANA timezone name (e.g. `Africa/Tunis`, `Europe/Paris`) THEN `timezone.iana_timezone` (named rule `IanaTimezone`). Case-sensitive.
- **REQ-0.4** Every validation failure SHALL answer `422 validation.failed` with a code per field, and change nothing.

### US-1: Personal books

- **REQ-1.1** WHEN a signed-in user posts `name`, an optional `currency` and an optional `timezone`, and no `household_id`, to `POST /api/v1/books` THE SYSTEM SHALL create a personal book owned by the user and answer `201` with the book.
- **REQ-1.2** WHEN `currency` is missing THE SYSTEM SHALL use `TND`; WHEN `timezone` is missing it SHALL use `Africa/Tunis`. This applies to every book.
- **REQ-1.3** IF the user already owns 5 personal books THEN THE SYSTEM SHALL answer `422 book.limit_reached` and create nothing. Concurrent requests SHALL NOT take a user past 5. The household's book is not counted.
- **REQ-1.4** THE SYSTEM SHALL NOT create any book or household when an account is created or signs in (D1).

### US-2: Households and the household's book

- **REQ-2.1** WHEN a signed-in user posts `name` to `POST /api/v1/households` THE SYSTEM SHALL create the household with the user as its `owner` and answer `201` with the household. It creates no book.
- **REQ-2.2** IF the user already belongs to a household, in any role, THEN THE SYSTEM SHALL answer `422 household.limit_reached` and create nothing. Concurrent requests SHALL NOT give a user two households.
- **REQ-2.3** WHEN the owner of a household posts `name`, an optional `currency`, an optional `timezone` and `household_id` to `POST /api/v1/books` THE SYSTEM SHALL create the household's book and answer `201` with the book.
  - IF `household_id` does not exist, is malformed, or names a household the caller is not a member of THEN `422 validation.failed` with `household_id.not_found`, the same in all three cases.
  - IF the caller is a `member` of that household THEN `403 household.forbidden`.
  - IF the household already has a book THEN `422 book.household_has_book`. Concurrent requests SHALL NOT give a household two books.
- **REQ-2.4** A household SHALL have `id`, `name` and `role`, the caller's role in it: `owner` or `member`.
- **REQ-2.5** WHEN a signed-in user calls `GET /api/v1/households` THE SYSTEM SHALL answer `200` with a list holding the user's household, or an empty list. Cursor pagination, like every list (CLAUDE.md, "API").
- **REQ-2.6** WHEN a member calls `GET /api/v1/households/{household}` THE SYSTEM SHALL answer `200` with the household.
- **REQ-2.7** WHEN the owner sends `name` to `PATCH /api/v1/households/{household}` THE SYSTEM SHALL rename it and answer `200` with the household. An empty body changes nothing and answers `200`. A `member` gets `403 household.forbidden` whatever the body.
- **REQ-2.8** Creating households and books SHALL be limited together to 10 per minute per user, then `429 request.throttled` with `retry_after_seconds`.
- **REQ-2.9** WHEN the owner calls `DELETE /api/v1/households/{household}` THE SYSTEM SHALL soft-delete the household and all its memberships in one transaction and answer `204`. A `member` gets `403 household.forbidden` and nothing changes.
- **REQ-2.10** Once a household is deleted, every former member SHALL be able to create a household (REQ-2.2) or, from spec 004 on, join one: the one-household rule counts only memberships of households that are not deleted. The deleted household answers `404 household.not_found` and is left out of `GET /households`.
- **REQ-2.11** WHEN a household is deleted THE SYSTEM SHALL soft-delete its book, if it has one. `Household` SHALL announce the deletion with a `HouseholdDeleted` event dispatched after commit, and `Ledger` SHALL react to it; `Household` never calls `Ledger`. The book then answers `404 book.not_found` and is left out of `GET /books`. Even before `Ledger` has reacted, nobody can reach the book, because access to a household's book needs a live membership (REQ-4.2).
- **REQ-2.12** Deleting a household SHALL remove no row: the household, its memberships and its book stay in the database, soft-deleted (D14).

### US-3: List my books

- **REQ-3.1** WHEN a signed-in user calls `GET /api/v1/books` THE SYSTEM SHALL answer `200` with the user's personal books and the book of the user's household, if any, and no others. Oldest first, cursor pagination. A user with no book gets an empty list.
- **REQ-3.2** Each book SHALL have:
  - `id`, `name` and `timezone`;
  - `currency` as `{code, exponent}`, the exponent coming from `Currency` (ICU);
  - `owner` as `{"type": "account", "id": …}` or `{"type": "household", "id": …}`;
  - `can_manage`: `true` for the user's personal books and for the household's book when the user is its owner, else `false`.

### US-4: Show a book

- **REQ-4.1** WHEN a user with access calls `GET /api/v1/books/{book}` THE SYSTEM SHALL answer `200` with the book.
- **REQ-4.2** A user has access to a personal book only if they own it, and to a household's book only if they are a member of that household, read at each request (so leaving a household in spec 004 removes access at once).

### US-5: Update a book

- **REQ-5.1** WHEN a user who can manage the book sends `name`, `timezone`, or both to `PATCH /api/v1/books/{book}` THE SYSTEM SHALL update them and answer `200` with the book.
  - Fields left out are unchanged; an empty body changes nothing and answers `200`.
  - A field that is sent must be valid: `name: ""` gives `name.required`.
- **REQ-5.2** IF the body contains `currency` THEN `422 validation.failed` with `currency.prohibited`, and nothing changes. IF it contains `household_id` THEN `household_id.prohibited`: a book never changes owner.
- **REQ-5.3** IF the caller has access but cannot manage the book THEN `403 book.forbidden`, whatever the body.
- **REQ-5.4** Changing the timezone SHALL change no stored data. It only changes how later specs compute periods such as "this month".

### US-7: List members

- **REQ-7.1** WHEN a member calls `GET /api/v1/households/{household}/members` THE SYSTEM SHALL answer `200` with each member's account `id`, `name` and `role`. Owners first, then by date joined. Cursor pagination.
- **REQ-7.2** A member SHALL never see another member's email, phone, locale or any other account field.
- **REQ-7.3** `Household` SHALL get member names from a read-only `Identity` action that returns names by account id (A1). The names are always current; `Household` keeps no copy.

### US-8: Access rules

- **REQ-8.1** Every route under `/api/v1/households` and `/api/v1/books` SHALL require a signed-in user, by web session or bearer token (spec 002, REQ-6.7). Without either: `401 auth.unauthenticated`, and no other check runs.
- **REQ-8.2** Every route under `/api/v1/households/{household}` SHALL pass through `RequireHousehold`, which checks membership and binds `CurrentHousehold`. Every route under `/api/v1/books/{book}` SHALL pass through `RequireBook`, which checks access (REQ-4.2) and binds `CurrentBook`. Specs 005+ nest money routes under `/api/v1/books/{book}/…`.
- **REQ-8.3** IF the household or book does not exist, the id is malformed, or the caller has no access THEN `404 household.not_found` / `404 book.not_found`. Status, body and headers SHALL be the same in all three cases, apart from `X-Request-Id`.
- **REQ-8.4** Every route SHALL be authorized by a policy: access first, then the right to manage where this spec requires it.
- **REQ-8.5** Checks SHALL run in this order: `401`, then `404`, then `403`, then `422`.
- **REQ-8.6** Every household SHALL always have exactly one `owner` in this spec. A user SHALL have at most one role in a household, and at most one household (D9).
- **REQ-8.7** Every member SHALL be able to read all of the household book's data (D6).
- **REQ-8.8** Every book SHALL have exactly one owner, an account or a household; the database SHALL refuse a book without one (D14).
- **REQ-8.9** Books, households and memberships SHALL be soft-deleted only. A soft-deleted book or household SHALL be answered `404` like an unknown one, left out of every list, and not counted in the limits of REQ-1.3, REQ-2.2 and REQ-2.3 (D14).

### US-9: Module boundaries and checks

- **REQ-9.1** `Identity` SHALL NOT use any class of `Household` or `Ledger`; `Household` SHALL NOT use any class of `Ledger`. Architecture tests SHALL enforce this.
- **REQ-9.2** In `Identity`, this spec SHALL add only the read-only names action (REQ-7.3).
- **REQ-9.3** `Household` SHALL own its tables (households, household memberships); `Ledger` SHALL own `books`. Neither reads or writes another module's tables. `Ledger` asks `Household` through a `Household` action for the caller's household and role, and listens to `HouseholdDeleted` (REQ-2.11). Ids and foreign keys may be shared.
- **REQ-9.4** Logs SHALL hold no household or book name, account name, email or other personal data.
- **REQ-9.5** Every endpoint SHALL have feature tests for success, validation errors (where it takes input), `401`, and another user's or household's record (`404`), at least once in cookie mode and once in token mode.
- **REQ-9.6** Every rule above SHALL have a test whose name starts with its `REQ` id.
- **REQ-9.7** The Postman collection SHALL get one request per new route in a `003 Books and households` folder, storing the returned `household_id` and `book_id` in variables.
- **REQ-9.8** `CLAUDE.md` SHALL be updated in this spec: `Ledger` owns `books`; "Household scoping" becomes book scoping (`book_id` on every money table, routes nested under `/books/{book}`, `RequireBook`/`CurrentBook`); currency and timezone belong to the book; the "Seeding" rule becomes D13 (a global two-level category list seeded in the database, with an "Other" child under every parent and an "Other" parent, and the typed name saved), with details left to spec 005.

Checklist:
- [x] Validation errors (per field, with stable codes): REQ-0.1 to REQ-0.4, REQ-2.3, REQ-5.1, REQ-5.2
- [x] Unauthenticated access (401), cookie and token modes: REQ-8.1, REQ-9.5
- [x] Access to another user's or household's data: REQ-2.3, REQ-4.2, REQ-8.3, REQ-8.5
- [x] Rate limits / repeated requests / duplicates: REQ-1.3, REQ-2.2, REQ-2.3, REQ-2.8
- [x] Money edge cases: no amounts in this spec. Currency validation and exponent: REQ-0.2, REQ-3.2
- [x] Date & timezone edge cases: REQ-0.3, REQ-5.4
- [x] Deleting / changing referenced data: deleting a household frees its members and hides its book, all soft-deleted (REQ-2.9 to REQ-2.12, REQ-8.9). Currency and owner never change (REQ-5.2); timezone change (REQ-5.4)

## Non-goals

- Invitations, joining, leaving, removing members, changing roles (spec 004).
- Categories (D13), transactions, recurring rules and summaries (spec 005+). Left for spec 005: the category list and its codes, how the app translates them, whether an "Other" name typed once is offered again later in the same book, and how summaries group "Other" entries.
- Deleting a book on its own, deleting an account, and restoring a deleted household or book.
- Changing a book's currency or owner, or moving records between books.
- Combined totals across several books.
- A "current" or "default" book stored on the server. The app chooses which book to show.
- Changes to `GET /me` or any other `Identity` endpoint.
- The frontend.

## Open questions

None. Every point was answered on 2026-11-19 and 2026-11-20 (see **Owner decisions**).
