# Household Expenses — API

Laravel API for a household expenses app. A household records its shared income, expenses and savings, and sees where the money goes.

- **Client:** the Angular app `expenses-management`, later also packaged with Capacitor for Android and iOS.
- **Status:** spec 001 (foundations), spec 002 (accounts: sign-up, sign-in on the web and on mobile, password reset, Google and two-step sign-in), spec 003 (books and households: personal books, one household per user with its book, members list), spec 004 (invitations and members: invite by email or QR code, accept or decline, remove a member, leave) and spec 005 (categories: the shared three-level category tree) are done. Transactions arrive with spec 006.
- **Working rules:** [CLAUDE.md](CLAUDE.md) holds the architecture, coding and security rules. Every feature starts as a spec in [specs/](specs/).

## Stack

| Piece | Version |
|---|---|
| PHP | 8.4.27 or newer (8.4 line) |
| Laravel | 13 |
| MySQL | 8.4 LTS, in every environment, tests included |
| Auth | Laravel Sanctum: cookie session for the web app, one token per device for mobile |
| Tests and checks | Pest, Pint, Larastan level 6 |

There is no Redis: cache, queues, sessions and rate limits use Laravel's `database` drivers.

## Getting started

You need Docker. PHP, Composer and Artisan run inside the app container, never on the host.

1. Create your environment file:

   ```bash
   cp .env.example .env
   ```

2. Fill in the empty secrets in `.env`: `DB_PASSWORD` and `DB_ROOT_PASSWORD`. Any long random strings will do.
3. Start the stack:

   ```bash
   docker compose up -d --build
   ```

4. Generate the app key, create the tables and load the category list:

   ```bash
   docker compose exec personal-accounting-backend php artisan key:generate
   docker compose exec personal-accounting-backend php artisan migrate
   docker compose exec personal-accounting-backend php artisan ledger:sync-categories
   ```

5. Check that the API answers:

   ```bash
   curl http://localhost:8000/api/v1/health
   # {"data":{"status":"ok"}}
   ```

MySQL creates the `expenses_db` and `expenses_test` databases and the `expenses` user the first time its volume is created. To start over from an empty database, run `docker compose down -v`, then `docker compose up -d --build`.

### Local services

All ports listen on `127.0.0.1` only. If a port is already taken on your machine, change it in `.env`.

| Service | Default URL | `.env` key |
|---|---|---|
| API (nginx) | http://localhost:8000 | `APP_FORWARD_PORT` |
| phpMyAdmin (local only) | http://localhost:8080 | `PMA_FORWARD_PORT` |
| MySQL | `127.0.0.1:3306` | `DB_FORWARD_PORT` |

The `queue` service runs `php artisan queue:work` and sends the code emails, in French or English depending on the account. Locally, mail goes to the log (`MAIL_MAILER=log`), so emailed codes can be read in `storage/logs/laravel.log`, or set `AUTH_TEST_MODE=true` to use `000000`.

### Authentication settings

| `.env` key | What it does |
|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | Web app domains that get a cookie session (e.g. `localhost:4200`). List only web domains: a mobile app's origin here would be treated as a browser. |
| `CORS_ALLOWED_ORIGINS` | Origins allowed to call the API with credentials (e.g. `http://localhost:4200`). |
| `SESSION_SECURE_COOKIE` | `true` everywhere except local http, where it must be `false`. |
| `SANCTUM_TOKEN_EXPIRATION` | Mobile token lifetime in minutes (default 90 days). |
| `TERMS_VERSION` | Terms version a new account must accept. |
| `GOOGLE_CLIENT_IDS` | Comma-separated Google OAuth client ids accepted for Google sign-in. |
| `AUTH_TEST_MODE` | `true` makes every emailed code `000000` and accepts Google tokens `test:<email>`. Ignored in production. |

A web client calls `GET /sanctum/csrf-cookie`, then signs in. A mobile client sends a `device_id` (a UUID per install) when it signs in and gets a token. Endpoints are listed in [specs/002-accounts/contract.md](specs/002-accounts/contract.md).

### Postman

[postman/](postman/) holds a collection of every route and a local environment. Import both, select **Household Expenses — local**, and set `AUTH_TEST_MODE=true` in `.env` so every code is `000000`. Requests run in mobile (token) mode, and sign-in answers store `token` and `pending_sign_in` for the next requests; creating a household or a book stores `household_id` or `book_id`.

## Everyday commands

```bash
docker compose exec personal-accounting-backend php artisan test --compact
docker compose exec personal-accounting-backend php artisan test --compact --filter=<name>
docker compose exec personal-accounting-backend vendor/bin/pint
docker compose exec personal-accounting-backend vendor/bin/phpstan analyse
docker compose exec personal-accounting-backend composer audit
docker compose exec personal-accounting-backend php artisan route:list --path=api
docker compose exec personal-accounting-backend php artisan ledger:sync-categories
```

Tests always run against MySQL `expenses_test`. The suite refuses to start on any database whose name doesn't end in `_test`.

## Project layout

```
app/
  Modules/<Module>/   one folder per business domain, added by specs (Identity, Household, Ledger)
  Shared/             cross-cutting code: errors, JSON-only middleware, request ids, Money, health
  Providers/
specs/                one folder per spec: requirements, design, tasks, API contract
tests/Architecture/   rules that every class under app/ must follow
```

## API conventions

- Every route lives under `/api/v1` and speaks JSON only. A request body that isn't a JSON object or array gets `415`.
- Errors carry a stable code and never a message: `{"error": {"code": "request.not_found"}}`. Validation errors list codes per field: `{"error": {"code": "validation.failed", "fields": {"amount": ["amount.required"]}}}`.
- Every response has an `X-Request-Id` header, which also appears in the logs.
- Money belongs to a **book**: a personal book of one account, or the book of a household. Each book has its currency (fixed at creation) and timezone.
- Amounts are integers in the currency's minor unit (millimes for TND, cents for EUR), sent along with an ISO 4217 currency code.
- Lists use cursor pagination: 25 items per page, `?cursor=` with the `meta.next_cursor` of the previous page. The category tree is the one exception: it comes whole, with an `ETag`.
- A record you may not see answers `404`, exactly like one that does not exist.

Each spec's `contract.md` lists its endpoints and error codes. The shared rules are in [specs/001-foundations/contract.md](specs/001-foundations/contract.md).

## Categories

Every book shares one category list with three levels: top category → group → leaf. Users only pick a leaf. The list lives in [app/Modules/Ledger/resources/categories.php](app/Modules/Ledger/resources/categories.php), and `GET /api/v1/categories` serves it (see [specs/005-categories/contract.md](specs/005-categories/contract.md)). The API sends translation keys (`categories.<code>.label`), never names: the app translates them.

### Changing the category list

1. Edit `app/Modules/Ledger/resources/categories.php`. You can add a category, change an emoji, move a category within its parent, or remove a category.
2. Never rename or reuse a code, and never change a top category's type. Add a new code instead. A code is the public identifier that entries point to.
3. Every group must end with its `other` leaf. Top categories and groups need an emoji.
4. Run the command. It checks the file first and changes nothing if any rule is broken, listing every problem:

   ```bash
   docker compose exec personal-accounting-backend php artisan ledger:sync-categories
   # Categories: 1 created, 0 updated, 0 retired, 0 restored, 169 unchanged.
   ```

   `--file=<path>` checks and syncs another file, for example a draft.
5. Add the names of new categories to the app's `fr.json`, `en.json` and `ar.json`, under `categories.<code>.label`. Until then the app shows the parent's name.

A category removed from the file is **retired**, not deleted: entries that use it keep it, the API still returns it with `retired: true`, and the app stops offering it. Put it back in the file to restore it.

## Deploying

Hosting is not chosen yet. Whatever it is, every deploy runs, in this order:

```bash
php artisan migrate --force
php artisan ledger:sync-categories
```

## Contributing

- No feature code is written before its spec is approved. See the workflow in [CLAUDE.md](CLAUDE.md).
- One feature or fix per branch, and one commit per pull request.
- CI runs Pint, Larastan, `composer audit` and the tests on every pull request and every push to `main`. A red check blocks the merge.
