# Onboarding channels

Most platforms built on SendSeven need their customers to connect their own WhatsApp number, Facebook Page, Instagram account or Telegram bot. SendSeven handles the provider flows (including Meta's **Embedded Signup** for WhatsApp) on a hosted, white-label page. You create a link, send the customer there, and they come back connected.

There is no public API for running Meta's Embedded Signup yourself; connect links are how SendSeven exposes it.

## Create a link

```php
use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Onboarding\ConnectLink;
use Reshapify\SendSeven\Onboarding\WhatsAppMode;

$link = $sendseven->connectLinks()->create(
    ConnectLink::for(ChannelType::WhatsApp)
        ->modes(whatsapp: [WhatsAppMode::Classic]) // hide Coexistence
        ->brandedAs('Acme')                         // shown on the connect page
        ->redirectTo('https://app.acme.test/channels/connected')
        ->named('Customer 1042')                    // your own label
        ->singleUse()                               // 1 connection
        ->expiresIn(hours: 48),                     // 1–168, default 24
);

$link->id;          // keep it: channel.created carries it back
$link->connectUrl;  // send the customer here
```

For a platform with a sub-account per customer, create the link in their tenant so the channel lands there:

```php
$sendseven->forTenant($customerTenantId)->connectLinks()->create($link);
```

## What can be connected

`ConnectLink::for()` accepts WhatsApp, Telegram, Instagram, Messenger and SMS, and the email providers as strings: `gmail`, `smtp_imap`, `sendgrid_byok`, `mailgun_byok` and `sendgrid_managed`.

Two channels are set up differently:

- **RCS** is provisioned by SendSeven itself after Google and the carriers verify the business. It's available only in Germany, to accounts billed there. See [channels](channels.md).
- **Browser push** comes from a website widget with push enabled (`widgets()->create(config: ['pushEnabled' => true, ...])`), installed with `widgets()->getEmbedCode()`.

## Connect modes

Some channels connect in more than one way:

| Channel | Modes |
|---|---|
| WhatsApp | `Classic` (Cloud API through Embedded Signup), `Coexistence` (the number keeps working in the WhatsApp Business app too) |
| Instagram | `Messaging` (DMs), `Social` (comments) |
| Messenger | `BusinessMessaging`, `BusinessSocial`, `PersonalSocial` |

Leave modes out to allow everything the account's plan supports. Modes only ever narrow the choice: a link can't unlock a mode the account doesn't have, and the connect page shows only what's actually allowed.

## When the customer isn't the Meta admin

The person setting things up often isn't an admin of the Meta Business portfolio that owns the number or Page. Email the link to whoever is:

```php
$sendseven->connectLinks()->delegate(
    email: 'it@customer.test',
    channelTypes: ['whatsapp'],
    recipientName: 'Sam',
    note: 'Please connect our WhatsApp number for Acme notifications.',
);
```

Delegated links are deliberately tight: two uses (one attempt plus one retry), a 60-minute window after first use, and one platform. The response always includes `connectUrl`, even if the email fails.

## Knowing when it's done

Subscribe to `channel.created`. Its channel carries the link that created it:

```php
use Reshapify\SendSeven\Webhooks\Events\ChannelEvent;

if ($event instanceof ChannelEvent && $event->wasConnectedVia($customer->connect_link_id)) {
    $customer->update(['sendseven_channel_id' => $event->channel->id]);
}
```

This beats syncing `channels()->list()` when the customer is redirected back: the webhook arrives even if they close the tab.

## Managing links

```php
$sendseven->connectLinks()->listTokens(includeExpired: false);
$sendseven->connectLinks()->getToken($id);
$sendseven->connectLinks()->getTokenAuditLogs($id);   // who opened it, what they connected
$sendseven->connectLinks()->revokeToken($id);
```

For WhatsApp numbers connected with Coexistence, check the sync state with `whatsApp()->getCoexistenceStatus($channelId)`.
