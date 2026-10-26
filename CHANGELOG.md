# Changelog

All notable changes to `sendseven` will be documented in this file.


## Unreleased

- Connect links can send the customer back by themselves: `ConnectLink::redirectTo($url, RedirectMode::AutoAfterFirst)`, or `partnerRedirectMode` on `createToken()`. Without a mode SendSeven shows a "Continue" button, as before. Connect tokens report `partnerRedirectMode`.

## 0.2.0 - 2026-10-13

- `messages()->sendText()`: sends text to a contact or address and throws `MessageFailed` when SendSeven answers 2xx with a failed message.
- `messages()->sendButtons()` and `sendList()`: trim to WhatsApp's limits and fall back to a numbered text message when SendSeven refuses or fails the interactive message (`fallbackToText: false` to rethrow instead).
- `contacts()->findByPhone()` and `findOrCreateByPhone()`: an exact match on the digits, never a fuzzy search hit.
- Webhook messages: `images()` (every image, including `photo` and untyped ones), `location()`, and `typedText()` (null for SendSeven's own summaries of reactions, stickers, locations and contacts).
- `buttonReply()` also reads `meta.interactive`, template quick-reply payloads (`meta.button.payload`), `list_reply` attachments and `row_id`/`payload`/`id`, and ignores blank ids. Interactive replies are now checked before `meta.button`.

## 0.1.3 - 2026-10-26

- Connect links can send the customer back by themselves: `ConnectLink::redirectTo($url, RedirectMode::AutoAfterFirst)`, or `partnerRedirectMode` on `createToken()`. Without a mode SendSeven shows a "Continue" button, as before. Connect tokens report `partnerRedirectMode`. (Backported to the 0.1 line.)

## 0.1.2 - 2026-10-01

- A successful response with no body (204, or an empty 200) reads as an empty object instead of throwing.

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
