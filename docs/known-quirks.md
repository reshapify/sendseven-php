# Known quirks

Where SendSeven's live behaviour differs from its OpenAPI spec or its prose docs, and what the SDK does about it. Each entry says when and how it was verified. Generated code picks up spec corrections from `openapi/patches/`.

| What | Reality | Handled by | Verified |
|---|---|---|---|
| Webhook verification | The endpoint-creation challenge is **unsigned** and must be answered with `{"challenge": "..."}`, or the endpoint never activates | `Webhook::isVerificationChallenge()`, `challengeResponse()` | Live, promo-next and TradeOps, Sep 2026 |
| Webhook signature | `sha256=HMAC(secret, "{X-SendSeven-Timestamp}.{raw body}")` in `X-SendSeven-Signature`. Not a body-only HMAC, and not `X-Webhook-Signature` (sendseven.com's own guide uses that name) | `WebhookVerifier` | Live deliveries, Sep 2026; docs.sendseven.com |
| Inbound message fields | The sender is `message.from_id` and the type `message.message_type`; files are in `message.attachments[]` (`signed_url`, `url`, `content_type`) | `Webhooks\Data\Message` | Live capture (`tests/Fixtures/webhooks/live-contract.json`) |
| Failure reasons | Under `message.meta.error` and `message.meta.error_code`, not top-level fields | `Message::errorCode()`, `errorMessage()` | docs.sendseven.com, Oct 2026 |
| Button replies | Arrive as `meta.button`, a `button_reply` attachment, or `interactive.list_reply` | `Message::buttonReply()` | TradeOps payloads, Sep 2026 |
| `contact.subscribed` and `contact.unsubscribed` | List (newsletter) subscriptions, not browser push. No field-level payload is documented | Parsed as `UnknownEvent` | docs.sendseven.com, Oct 2026 |
| `POST /whatsapp-templates/translate` | Exists, but missing from the spec. Requires `body_text` and `target_language` | `openapi/patches/undocumented-endpoints.json` | Live 422, 1 Oct 2026 |
| `POST /messages/proxy` | Exists, but missing from the spec. Passes a request to the channel's platform API (e.g. WhatsApp read receipts) | Patch; `messages()->markWhatsAppMessageAsRead()` | Live 422, 1 Oct 2026 |
| RCS wallet endpoints | Documented in prose, missing from the spec | Patch (`rcsWallet()`) | docs.sendseven.com, Oct 2026 |
| `PUT /tenants/{tenant_id}` and `.../channel-priority` | The spec omits the `tenant_id` path parameter | The generator infers undeclared path parameters | Contract test, 1 Oct 2026 |
| `MessageCreate.to` | Typed as anything in the spec; it's a string address | Patch | docs.sendseven.com |
| Creating tenants | Needs the `multi_tenant` plan feature **and** a token from the billing account's owner | `FeatureDisabled`, `NotBillingAccountOwner`, `Client::capabilities()` | Live refusals, 1 Oct 2026 |
| Viber | Listed in `ChannelType`, but has no connect flow, capabilities or docs | Kept in the enum; not offered by `ConnectLink` | docs.sendseven.com, Oct 2026 |
| RCS availability | Germany only, for accounts billed in DE; provisioned by SendSeven, not through connect links | `ConnectLink::for()` refuses RCS with an explanation | docs.sendseven.com, Oct 2026 |

Found another? Add a patch with an `x-source` saying how you verified it, regenerate, and add a row here.
