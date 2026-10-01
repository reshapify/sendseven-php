# Changelog

All notable changes to `sendseven` will be documented in this file.


## 0.1.1 - 2026-10-01

- A field the spec marks as required but a response leaves out now reads as an empty value (`''`, `0`, `false`, `[]`, the Unix epoch) instead of throwing `UnexpectedResponse`. A field present with the wrong type still throws. SendSeven's spec has been wrong about required fields before, and a missing field nobody reads shouldn't break a request.

## 0.1.0 - 2026-10-01

- Every SendSeven endpoint (712 operations in 71 resources), generated from SendSeven's OpenAPI spec plus verified corrections in `openapi/patches`.
- Typed responses, enums that tolerate new values, pagination with `lazy()`.
- Webhooks: the verification challenge, timestamped signature checks and typed events.
- Connect links for customer channel onboarding (WhatsApp Embedded Signup, Instagram, Messenger, Telegram, SMS, email).
- Plan and tenancy awareness via `capabilities()`; typed exceptions whose messages say how to fix the problem.
- Retries with idempotency keys, `Retry-After` and a pluggable rate limiter.
- `SendSeven::fake()` for tests.
- Docs for people and agents: README, guides, known quirks, a reference page per resource, `AGENTS.md`, a Claude skill, `llms.txt`, `llms-full.txt` and `openapi/manifest.json`.
