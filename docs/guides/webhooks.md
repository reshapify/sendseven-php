# Webhooks

SendSeven posts events to your endpoint: messages in, delivery updates out, channels connecting and disconnecting, contacts changing.

## Three rules

1. **Answer the challenge.** When an endpoint is created (or its URL changes), SendSeven sends one **unsigned** request and only activates the endpoint if you echo its `challenge`. A handler that rejects unsigned requests never receives a single event.
2. **Verify everything else**, using the raw body exactly as received. Parsing and re-encoding the JSON changes the bytes and breaks the signature.
3. **Acknowledge fast.** Return 2xx within 30 seconds and do slow work in a queue. Failed deliveries are retried, and an endpoint that keeps failing is suspended.

```php
use Reshapify\SendSeven\Webhooks\Webhook;
use Reshapify\SendSeven\Webhooks\InvalidSignature;

if (Webhook::isVerificationChallenge($body, $headers)) {
    return json_response(Webhook::challengeResponse($body)); // {"challenge": "..."}, status 200
}

try {
    $event = Webhook::constructEvent($body, $headers, $secret);
} catch (InvalidSignature $exception) {
    return response($exception->getMessage(), 401);
}

dispatch(new HandleSendSevenEvent($event->raw())); // then return 200
```

`$headers` can be any shape frameworks give you: `['X-SendSeven-Signature' => '...']`, PSR-7 lists, or `$_SERVER` keys like `HTTP_X_SENDSEVEN_SIGNATURE`. For a PSR-7 request, use `Webhook::constructEventFromRequest($request, $secret)`.

## How SendSeven signs deliveries

```
X-SendSeven-Timestamp: 1770000000
X-SendSeven-Signature: sha256=<hex HMAC-SHA256 of "1770000000.<raw body>" with the endpoint's secret>
```

- The timestamp is part of what's signed, so an old delivery can't be replayed. Deliveries more than 300 seconds from your clock are refused; change it with `toleranceSeconds:`.
- The secret is shown **once**, when the endpoint is created (`webhooks()->createEndpoint()` returns it). Store it; `regenerateSecret()` replaces it.
- If you registered the endpoint with an `authorizationHeader`, pass the same value as `authorization:` and it's checked as a second, independent gate.

The SDK's earlier relatives got this wrong twice, by signing the body alone and by reading `X-Webhook-Signature`. Both silently reject every real event. `WebhookVerifier::signature()` is the reference implementation.

## Registering an endpoint

```php
use Reshapify\SendSeven\Webhooks\EventType;

$endpoint = $sendseven->webhooks()->createEndpoint(
    name: 'My app',
    url: 'https://app.example.com/webhooks/sendseven', // HTTPS and public
    subscribedEvents: array_map(fn ($type) => $type->value, EventType::messaging()),
);

$secret = $endpoint->secretKey; // store it now
```

Your endpoint must answer the challenge immediately, so deploy the handler first. If verification failed, fix the handler and call `webhooks()->verifyEndpoint($webhookId)`.

## Events

| Event class | Types | Carries |
|---|---|---|
| `MessageReceived` | `message.received` | `message`, `conversation`, `contact`, `contactMethod` |
| `MessageStatusUpdated` | `message.sent`, `.delivered`, `.read`, `.failed` | `message`; `status()`, `failed()` |
| `MessageReactionChanged` | `message.reaction` | `reaction` (`emoji`, `added`), `message` |
| `ChannelEvent` | `channel.created`, `.updated`, `.deleted` | `channel`; `change()`, `wasConnectedVia($linkId)` |
| `ContactEvent` | `contact.created`, `.updated`, `.deleted` | `contact` |
| `ConversationEvent` | `conversation.created`, `.closed`, `.assigned`, `.reopened`, `.updated` | `conversation`, `contact` |
| `UnknownEvent` | everything else (campaign, comment, post, email, link and new types) | `data` |

Every event has `id` (unique per event: use it to ignore redeliveries), `eventId` (the underlying occurrence; often the message ID), `createdAt`, `tenantId`, `type` and `raw()`.

### Messages

- `message->fromId` is the sender: a phone number on WhatsApp, SMS and RCS, or the channel's ID for the person on Telegram, Messenger and Instagram.
- `message->type` is SendSeven's `message_type`: `text`, `image`, `video`, `audio`, `document`, `sticker`, `location`, `contact`, `interactive`, `button`, `reaction`…
- `message->text` is the text, or a media caption (`""` when there is none).
- `message->attachment()` gives the first file. Use `downloadUrl()`: the signed URL needs no token but expires 24 hours after the event; `url` needs the API token and never expires.
- `message->buttonReply()` gives the button or list row tapped. SendSeven reports it in three ways (`meta.button`, a `button_reply` attachment, or `interactive.list_reply`), and this checks all three.
- `message->errorCode()` and `errorMessage()` explain a failure: Meta's codes such as `131047` (outside the 24-hour window), or SendSeven's such as `rcs_not_reachable`.
- `message->contactInfo()` holds sender details SendSeven sometimes adds: name, phone, WhatsApp username and business-scoped ID.

### Channels

`channel.created` fires when a customer finishes a connect link, so there's no need to poll:

```php
if ($event instanceof ChannelEvent && $event->wasConnectedVia($storedConnectLinkId)) {
    // the channel your customer just connected: $event->channel->id
}
```

`channel.updated` with `change() === 'status_changed'` and `! $event->channel->isConnected()` means a channel went offline; `disconnectionReason` says why (`token_invalidated`, `partner_removed`…). Prompt the customer to reconnect.

### Not what it sounds like

`contact.subscribed` and `contact.unsubscribed` are about **lists** (newsletter subscriptions), not browser notifications. SendSeven doesn't document their field-level payload, so they arrive as `UnknownEvent`.

## Testing webhook handlers

```php
$body = json_encode([...]);
$headers = Webhook::sign($body, 'test-secret'); // X-SendSeven-Timestamp and X-SendSeven-Signature

$response = $this->postJson('/webhooks/sendseven', json_decode($body, true), $headers);
```

`tests/Fixtures/webhooks/live-contract.json` in this repository holds deliveries captured from the live API, which are useful as realistic fixtures.
