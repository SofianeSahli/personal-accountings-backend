# <NNN> — <Feature name>: API contract

> Base: `/api/v1` · JSON only · amounts are integers in minor units + `currency`

## <METHOD> <path>
Auth: cookie session or bearer token · Policy: …

Request
```json
{}
```

Response `200`
```json
{"data": {}}
```

Errors
| Status | Code | When |
|---|---|---|
| 401 | `auth.unauthenticated` | no session or token |
| 422 | — | `fields` with per-field codes |
