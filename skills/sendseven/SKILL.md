---
name: sendseven-php
description: Use when writing PHP that talks to the SendSeven messaging API (WhatsApp, SMS, email, Telegram, Messenger, Instagram, RCS, browser push) with reshapify/sendseven: sending messages, receiving and verifying webhooks, letting customers connect channels (WhatsApp Embedded Signup via connect links), multi-tenant/partner setups, or testing SendSeven integrations.
---

# SendSeven PHP SDK

`composer require reshapify/sendseven` (Laravel: `reshapify/sendseven-laravel`).

## Find the method

Search `vendor/reshapify/sendseven/openapi/manifest.json` for the endpoint (by path, operationId or summary). Each entry has `call`, `parameters` (name, type, required) and `returns`. Reference pages: `vendor/reshapify/sendseven/docs/reference/`.

## Core patterns

```php
use Reshapify\SendSeven\SendSeven;

$sendseven = SendSeven::client($token);

// Send. Named arguments; omitted ones aren't sent.
$sendseven->messages()->send(to: '+4915112345678', channelId: $channelId, text: 'Hi');

// WhatsApp outside the 24-hour window needs a template
$sendseven->whatsAppTemplates()->send(templateIdOrName: 'order_shipped', /* ... */);

// Lists: Page<T>; ->lazy() walks all pages
foreach ($sendseven->contacts()->list(search: 'acme')->lazy() as $contact) {}
```

## Webhooks: the order matters

```php
use Reshapify\SendSeven\Webhooks\Webhook;

if (Webhook::isVerificationChallenge($rawBody, $headers)) {
    return json(Webhook::challengeResponse($rawBody));   // or the endpoint never activates
}

$event = Webhook::constructEvent($rawBody, $headers, $secret); // raw body, as received
```

- Event classes: `MessageReceived`, `MessageStatusUpdated` (`failed()`, `message->errorCode()`), `MessageReactionChanged`, `ChannelEvent` (`wasConnectedVia($linkId)`), `ContactEvent`, `ConversationEvent`, `UnknownEvent`.
- The sender is `message->fromId`; a tapped button is `message->buttonReply()?->id`; a file is `message->attachment()?->downloadUrl()`.
- The signature is `sha256=HMAC(secret, "{X-SendSeven-Timestamp}.{body}")` in `X-SendSeven-Signature`. Never implement a body-only HMAC.

## Customers connecting their own channels

```php
use Reshapify\SendSeven\Onboarding\{ConnectLink, WhatsAppMode};
use Reshapify\SendSeven\Enums\ChannelType;

$link = $sendseven->connectLinks()->create(
    ConnectLink::for(ChannelType::WhatsApp)->modes(whatsapp: [WhatsAppMode::Classic])
        ->brandedAs('Brand')->redirectTo($url)->singleUse(),
);
// redirect to $link->connectUrl; store $link->id; the channel.created webhook carries it back
```

This is how WhatsApp Embedded Signup works on SendSeven. RCS (Germany only, set up by SendSeven) and browser push (a website widget) aren't connected this way.

## Tenancy

A token is one tenant, which is enough for most apps. `forTenant($id)` and `tenants()->create()` need multi-tenant management on the plan (Professional, Scale, Enterprise or API Only; not Basic or an ended trial), and creating tenants needs the billing account owner's token. Check with `$sendseven->capabilities()->canCreateTenants()` and report `whyNotCreateTenants()`.

## Errors

Catch `Reshapify\SendSeven\Exceptions\SendSevenException`. Specific classes: `ValidationFailed` (`fieldErrors()`), `FeatureDisabled` (`feature()`), `NotBillingAccountOwner`, `PermissionDenied`, `RateLimited` (`retryAfter()`), `InsufficientBalance`, `NotFound`, `AuthenticationFailed`, `ServerError`, `TransportFailed`. Messages include the fix; show them to the user.

## Testing

```php
$fake = SendSeven::fake(['POST /messages' => ['id' => 'msg_1', 'direction' => 'outbound', 'message_type' => 'text']]);
// use $fake->client in place of the real client
$fake->assertSent('POST /messages', fn ($request) => $request->body['to'] === '+4915112345678');

$headers = Webhook::sign($body, 'secret'); // for webhook handler tests
```

## Before trusting SendSeven's own spec

Read `docs/known-quirks.md`: webhook signing, message field names, undocumented endpoints, RCS rules and tenancy requirements are recorded there with how each was verified.
