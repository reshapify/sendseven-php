# Testing

## Fake the API

`SendSeven::fake()` returns a fake whose `client` sends nothing. It answers from a script and records every request. Requests still go through the real pipeline (headers, idempotency keys, error mapping), so a scripted 422 throws `ValidationFailed` just as SendSeven would. Retries are off, so a scripted failure fails once.

```php
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\SendSeven;

$fake = SendSeven::fake([
    'POST /messages' => ['id' => 'msg_1', 'direction' => 'outbound', 'message_type' => 'text'],
    'GET /contacts/*' => ['id' => 'ct_1', /* ... */],
    'POST /tenants' => new Response(403, '{"detail":{"code":"feature_disabled","feature":"multi_tenant"}}'),
]);

$notifier = new OrderNotifier($fake->client);
$notifier->shipped($order);

$fake->assertSent('POST /messages');
$fake->assertSent('POST /messages', fn (Request $request) => $request->body['to'] === '+4915112345678');
$fake->assertNotSent('DELETE *');
$fake->assertSentCount(1, 'POST /messages');
$fake->requests(); // every Request, in order
```

**Script values** can be:
- an array, sent as JSON with status 200, or 201 for POST;
- a `Response`, for a specific status, headers or body;
- a closure that receives the `Request` and returns either of those;
- a list of any of these, used in turn, with the last one repeating.

**Patterns** are `METHOD /path`; `*` matches any run of characters, and a bare `*` matches anything.

A request with no scripted answer throws `UnexpectedRequest`, saying how to script it.

## Inject the client

Type-hint `Reshapify\SendSeven\Client` in your services and build it in one place (a container binding or factory), so tests can pass `$fake->client`.

## Test webhook handlers

```php
use Reshapify\SendSeven\Webhooks\Webhook;

$body = json_encode([
    'id' => 'evt_1',
    'type' => 'message.received',
    'data' => ['message' => ['id' => 'msg_1', 'message_type' => 'text', 'text' => 'Hi', 'from_id' => '+4915112345678', 'direction' => 'inbound']],
]);

$headers = Webhook::sign($body, 'test-secret'); // X-SendSeven-Timestamp and X-SendSeven-Signature
```

Post `$body` with those headers to your endpoint, configured with `test-secret`. Test the challenge too: post `{"type":"sendseven_verification","challenge":"abc"}` with `X-SendSeven-Event: verification` and expect `{"challenge":"abc"}` back.

## Contract tests against the real API

This repository's own suite includes opt-in live tests (`tests/Live`) that make read-only calls. Run them with a test token:

```bash
SENDSEVEN_TEST_TOKEN=s7_api_... vendor/bin/pest --group=live
```
