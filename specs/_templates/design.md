# <NNN> — <Feature name>: Design

> Status: Draft | Approved (<date>)
> Requirements: [requirements.md](./requirements.md) · Contract: [contract.md](./contract.md)

## Overview
Short description of the approach.

## Placement
| Piece | Module / path | Reason |
|---|---|---|
| … | `app/Modules/<Module>/Actions/…` | … |

Cross-module calls (which actions or events of other modules are used, and why the dependency direction holds): …

## Data
| Table | Columns (type) | Indexes / FKs | Notes |
|---|---|---|---|

Migrations: …

## Actions
| Action | Input (DTO) | Rules enforced | Transaction? | Events |
|---|---|---|---|---|

## Endpoints
| Method | Path | Request | Response (resource) | Policy | Error codes |
|---|---|---|---|---|---|

Full request/response examples go in `contract.md`.

## Authorization
Policies and abilities, membership and role checks: …

## Error codes
- `<domain>.<reason>` — when it happens, HTTP status

## Requirement coverage
| REQ | Covered by |
|---|---|
| REQ-1.1 | … |

## Risks / alternatives considered
…
