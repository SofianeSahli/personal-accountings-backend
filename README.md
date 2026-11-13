# Household Expenses — API

Laravel API for a household expenses app. A household records its shared income, expenses and savings, and sees where the money goes.

- **Client:** the Angular app `expenses-management`, later also packaged with Capacitor for Android and iOS.
- **Status:** foundations only (spec 001). The API serves `GET /api/v1/health`; accounts, households and transactions arrive with specs 002–005.
- **Working rules:** [CLAUDE.md](CLAUDE.md) holds the architecture, coding and security rules. Every feature starts as a spec in [specs/](specs/).

## Stack

| Piece | Version |
|---|---|
| PHP | 8.4.27 or newer (8.4 line) |
| Laravel | 13 |
| MySQL | 8.4 LTS, in every environment, tests included |
| Auth | Laravel Sanctum (arrives with spec 002) |
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

4. Generate the app key and create the tables:

   ```bash
   docker compose exec personal-accounting-backend php artisan key:generate
   docker compose exec personal-accounting-backend php artisan migrate
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

## Everyday commands

```bash
docker compose exec personal-accounting-backend php artisan test --compact
docker compose exec personal-accounting-backend php artisan test --compact --filter=<name>
docker compose exec personal-accounting-backend vendor/bin/pint
docker compose exec personal-accounting-backend vendor/bin/phpstan analyse
docker compose exec personal-accounting-backend composer audit
docker compose exec personal-accounting-backend php artisan route:list --path=api
```

Tests always run against MySQL `expenses_test`. The suite refuses to start on any database whose name doesn't end in `_test`.

## Project layout

```
app/
  Modules/<Module>/   one folder per business domain (Identity, Household, Ledger), added by specs
  Shared/             cross-cutting code: errors, JSON-only middleware, request ids, Money, health
  Providers/
specs/                one folder per spec: requirements, design, tasks, API contract
tests/Architecture/   rules that every class under app/ must follow
```

## API conventions

- Every route lives under `/api/v1` and speaks JSON only. A request body that isn't a JSON object or array gets `415`.
- Errors carry a stable code and never a message: `{"error": {"code": "request.not_found"}}`. Validation errors list codes per field: `{"error": {"code": "validation.failed", "fields": {"amount": ["amount.required"]}}}`.
- Every response has an `X-Request-Id` header, which also appears in the logs.
- Amounts are integers in the currency's minor unit (millimes for TND, cents for EUR), sent along with an ISO 4217 currency code.

Each spec's `contract.md` lists its endpoints and error codes. The shared rules are in [specs/001-foundations/contract.md](specs/001-foundations/contract.md).

## Contributing

- No feature code is written before its spec is approved. See the workflow in [CLAUDE.md](CLAUDE.md).
- One feature or fix per branch, and one commit per pull request.
- CI runs Pint, Larastan, `composer audit` and the tests on every pull request and every push to `main`. A red check blocks the merge.
