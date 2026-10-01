# SendSeven for PHP

A typed PHP SDK for the [SendSeven](https://sendseven.com) messaging API: WhatsApp, SMS, email, Telegram, Messenger, Instagram, RCS and browser push, from one client.

- **Every endpoint.** All 712 operations, generated from SendSeven's OpenAPI spec and corrected where the live API differs.
- **Typed throughout.** Responses are readonly objects with real types; lists paginate lazily; enums stay open to new values.
- **Webhooks done right.** The activation challenge, timestamped signatures and typed events, verified against live deliveries.
- **Safe by default.** Idempotency keys on every write, retries only where safe, and errors that say how to fix them.
- **Built for tests.** `SendSeven::fake()` scripts responses and asserts requests through the real pipeline.

```bash
composer require reshapify/sendseven
```

Requires PHP 8.3+ and any PSR-18 HTTP client (Guzzle, Symfony HttpClient…), which is found automatically. Laravel? Use [reshapify/laravel-sendseven](https://github.com/reshapify/laravel-sendseven).

## Send a message

```php
use Reshapify\SendSeven\SendSeven;

$sendseven = SendSeven::client(getenv('SENDSEVEN_API_TOKEN'));

$message = $sendseven->messages()->send(
    to: '+4915112345678',
    channelId: 'ch_whatsapp',
    text: 'Your order has shipped.',
);

echo $message->id;
```

Every area of the API is a method on the client: `contacts()`, `channels()`, `whatsAppTemplates()`, `conversations()`, `campaigns()`, `webhooks()` and [65 more](docs/reference/README.md). Arguments are named and typed; optional ones are left out of the request.

## Lists

```php
// One page
$page = $sendseven->contacts()->list(search: 'acme', pageSize: 50);

foreach ($page as $contact) {
    echo $contact->name;
}

// Every contact, fetching pages only as they're reached
foreach ($sendseven->contacts()->list()->lazy() as $contact) {
    // ...
}
```

## Receive webhooks

```php
use Reshapify\SendSeven\Webhooks\Webhook;
use Reshapify\SendSeven\Webhooks\Events\MessageReceived;
use Reshapify\SendSeven\Webhooks\Events\MessageStatusUpdated;

$body = file_get_contents('php://input');   // the raw body, exactly as received
$headers = getallheaders();

// SendSeven activates an endpoint only after this unsigned challenge is echoed.
if (Webhook::isVerificationChallenge($body, $headers)) {
    header('Content-Type: application/json');
    echo json_encode(Webhook::challengeResponse($body));
    return;
}

$event = Webhook::constructEvent($body, $headers, $secret);   // throws InvalidSignature

if ($event instanceof MessageReceived) {
    $from = $event->message->fromId;              // phone, or the channel's ID for the person
    $text = $event->message->text;
    $button = $event->message->buttonReply()?->id; // a tapped button, however it arrived
}

if ($event instanceof MessageStatusUpdated && $event->failed()) {
    $why = $event->message->errorCode();          // e.g. "131047": outside WhatsApp's 24 hours
}
```

See the [webhooks guide](docs/guides/webhooks.md) for every event and how SendSeven signs them.

## Let customers connect their own channels

SendSeven runs WhatsApp's Embedded Signup (and Messenger, Instagram, Telegram, SMS and email onboarding) on a hosted page. Create a link, send the customer to it, and a `channel.created` webhook tells you when they're done.

```php
use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Onboarding\ConnectLink;
use Reshapify\SendSeven\Onboarding\WhatsAppMode;

$link = $sendseven->connectLinks()->create(
    ConnectLink::for(ChannelType::WhatsApp)
        ->modes(whatsapp: [WhatsAppMode::Classic])
        ->brandedAs('Acme')
        ->redirectTo('https://app.acme.test/channels/connected')
        ->singleUse()
        ->expiresIn(hours: 48),
);

header('Location: '.$link->connectUrl);
```

More in [onboarding channels](docs/guides/onboarding-channels.md).

## One tenant, or many

A token belongs to one SendSeven tenant (workspace), and everything works with it on any plan that includes the API. Platforms that give each customer their own sub-account can act on it with `forTenant()`:

```php
$sendseven->forTenant($customerTenantId)->contacts()->list();
```

That needs SendSeven's **multi-tenant management** (Professional, Scale, Enterprise or API Only; not Basic, or a trial that has ended), and creating tenants needs a token from the **billing account's owner**. Check before you rely on it:

```php
$capabilities = $sendseven->capabilities();

if (! $capabilities->canCreateTenants()) {
    echo $capabilities->whyNotCreateTenants();
}
```

See [tenancy and plans](docs/guides/tenancy.md).

## Errors

Every exception implements `Reshapify\SendSeven\Exceptions\SendSevenException`, and its message names the endpoint, the status and what to do:

```
feature_disabled (POST /tenants, HTTP 403, request req_8f2…). The account's SendSeven plan doesn't
include multi_tenant: it comes with Professional, Scale, Enterprise or API Only.
```

| Exception | When |
|---|---|
| `AuthenticationFailed` | 401: the token is wrong, expired or revoked |
| `PermissionDenied` | 403: the token lacks a scope |
| `FeatureDisabled` | 403: the plan lacks a feature; `feature()` names it |
| `NotBillingAccountOwner` | 403: only the billing account's owner can create tenants |
| `NotFound` | 404 |
| `ValidationFailed` | 422; `fieldErrors()` lists each field |
| `RateLimited` | 429 after retries; `retryAfter()` |
| `InsufficientBalance` | 402 or an `insufficient_*` code, e.g. the RCS wallet |
| `ServerError` | 5xx after retries |
| `TransportFailed` | SendSeven couldn't be reached |
| `UnexpectedResponse` | the response didn't match its documented shape |

## Retries, rate limits and idempotency

Writes get an `Idempotency-Key` automatically, so retrying them is safe; pass your own (`idempotencyKey: 'order-42-shipped'`) to make a repeat from your side safe too. 429s, 5xx and connection failures are retried up to three times with backoff, honouring `Retry-After`. A token allows 100 standard requests a minute: give processes that share it a shared `RateLimiter`. See [resilience](docs/guides/resilience.md).

```php
$sendseven = SendSeven::factory()
    ->withToken($token)
    ->withRetries(5)
    ->withRateLimiter($sharedLimiter)
    ->withHttpClient($psr18Client)
    ->make();
```

## Testing

```php
$fake = SendSeven::fake([
    'POST /messages' => ['id' => 'msg_1', 'direction' => 'outbound', 'message_type' => 'text', /* ... */],
]);

$service = new OrderNotifier($fake->client);
$service->shipped($order);

$fake->assertSent('POST /messages', fn ($request) => $request->body['to'] === '+4915112345678');
```

Webhook tests can sign their own deliveries with `Webhook::sign($body, $secret)`. See [testing](docs/guides/testing.md).

## Anything else

`$sendseven->request(Method::Get, '/some/new/endpoint')` calls any endpoint with the same authentication, retries and errors. `->raw()` on any response object returns the original JSON, including fields the SDK doesn't model.

## Documentation

- [API reference](docs/reference/README.md): every endpoint, its parameters, scopes and return type
- Guides: [webhooks](docs/guides/webhooks.md), [onboarding channels](docs/guides/onboarding-channels.md), [tenancy](docs/guides/tenancy.md), [channels](docs/guides/channels.md), [resilience](docs/guides/resilience.md), [testing](docs/guides/testing.md)
- [Known quirks](docs/known-quirks.md): where SendSeven differs from its spec
- For AI agents: [llms.txt](llms.txt), [AGENTS.md](AGENTS.md), and a [skill](skills/sendseven/SKILL.md)

## Contributing

`composer test` runs Pint, Rector, PHPStan (max), type coverage (100%) and Pest. Generated code is never edited by hand: change the generator or `openapi/patches`, then run `composer generate`. See [AGENTS.md](AGENTS.md).

This is a community SDK, not an official SendSeven product. MIT licensed.
