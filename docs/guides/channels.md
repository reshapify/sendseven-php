# Channels and identifiers

## Addressing people

| Channel | `to` is | Can you message first? |
|---|---|---|
| WhatsApp | Phone number (E.164) | Templates anytime; free text within 24 hours of their last message |
| SMS | Phone number (E.164) | Yes, in the countries you allow |
| RCS | Phone number (E.164) | Yes, to opted-in contacts; no window |
| Email | Email address | Yes |
| Telegram | Chat ID, scoped to **your** bot | Only after they've started your bot |
| Messenger | Page-scoped ID (PSID) | Within 24 hours of their last message |
| Instagram | IG-scoped ID | Within 24 hours of their last message |
| Browser push | — (a subscription) | Anytime, once they've allowed notifications |

Telegram, Messenger and Instagram IDs **only exist once the person messages you**, and only for your bot, Page or account. An ID from another system is meaningless, so they can't be imported. Grow these channels with links that start a conversation (`t.me/<bot>?start=...`, `m.me/<page>?ref=...`); see `lists()->getSignupOptions()`.

You can also send by contact instead of address: `messages()->send(contactId: ..., channelId: ...)` or `contactMethodId:`, and SendSeven resolves the address.

## Reply windows

`ChannelType::replyWindowHours()` gives 24 for WhatsApp, Messenger and Instagram, and null for the rest.

- **WhatsApp:** outside the window, only approved templates (`whatsAppTemplates()->send()`) are accepted. A free-form message fails with error `131047`.
- **Messenger and Instagram:** outside the window, sends are refused. Meta's Human Agent tag extends replies by a person (not automation) to 7 days, subject to Meta's review. Other message tags were retired in 2026.

## Limits that matter

| Channel | Text limit | Notes |
|---|---|---|
| WhatsApp | 4,096 | Captions 1,024; up to 3 reply buttons, lists 10×10 |
| Telegram | 4,096 | Up to 8 inline buttons |
| Messenger | 2,000 | Up to 3 buttons, 13 quick replies; no documents |
| Instagram | 1,000 | Quick replies; no documents or audio |
| RCS | 3,072 | Up to 11 suggestions; rich cards and carousels |
| SMS | — | Text only; long messages bill as several segments |

SendSeven splits over-length text into ordered messages instead of failing. `channels()->getCapabilities($channelId)` and `getFileSupport()` give the live limits for a channel.

## RCS

- Available **only in Germany**, to accounts whose billing country is DE (exceptions on request to SendSeven).
- SendSeven sets up each RCS agent after Google and the carriers verify the business. Connect links can't create one.
- Paid from a separate prepaid **RCS wallet** (first top-up at least EUR 100 net), charged per delivered message: `basic` (plain text of 160 characters or fewer), `single` (anything else; EUR 0.079 net by default), `session`, or `mau` for newsletter agents (EUR 0.19 per recipient per month). Each per-message send also counts as a SendSeven message. Your prices: `rcsWallet()->getPrices()`.
- No automatic SMS fallback: a phone that can't receive RCS fails with `rcs_not_reachable` and isn't charged.
- Insufficient balance throws `InsufficientBalance` (`insufficient_rcs_balance`).

## Pricing

SendSeven charges one per-message rate on every channel, which depends on the plan (EUR 0.005 on API Only at the time of writing). SMS adds the carrier cost per segment (`sms()->pricelist()` and `sms()->priceMessage()`). Meta bills WhatsApp conversation fees directly. Inbound messages are free, and reactions are never billed.
