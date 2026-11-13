# <NNN> — <Feature name>: Requirements

> Status: Draft | Approved (<date>)
> Scope: <module(s) affected>

## Context
Why this feature is needed and what problem it solves (2–5 sentences).

## User stories
- **US-1** — As a `<role>`, I want `<capability>`, so that `<benefit>`.

## Acceptance criteria (EARS)

### US-1
- **REQ-1.1** WHEN `<trigger>` THE SYSTEM SHALL `<response>`.
- **REQ-1.2** IF `<unwanted condition>` THEN THE SYSTEM SHALL `<response>`.
- **REQ-1.3** WHILE `<state>` THE SYSTEM SHALL `<response>`.

Checklist — have these been covered?
- [ ] Validation errors (per field, with stable codes)
- [ ] Unauthenticated access (401), cookie and token modes
- [ ] Access to another household's data
- [ ] Rate limits / repeated requests / duplicates
- [ ] Money edge cases (zero, negative, currency exponent)
- [ ] Date & timezone edge cases (household timezone, month boundaries)
- [ ] Deleting / changing referenced data

## Non-goals
- …

## Open questions
- [ ] … (must be answered by the user before approval)
