# Rate limits, retries and idempotency

## Idempotency

Every POST and PATCH gets an `Idempotency-Key` header automatically. If a retry repeats the request, SendSeven answers with the first result instead of acting twice.

Pass your own key when *your* code might repeat the call, for example a queued job that can run twice:

```php
$sendseven->messages()->send(
    to: $order->phone,
    channelId: $channelId,
    text: "Order {$order->number} shipped.",
    idempotencyKey: "order-{$order->id}-shipped",
);
```

Reusing a key with a different body is refused with `Conflict`.

## Retries

| Situation | Retried? |
|---|---|
| 429 Too Many Requests | Yes, after `Retry-After` when sent |
| 5xx | Yes, with exponential backoff and jitter |
| Connection failure, DNS, TLS, timeout | Yes |
| GET, PUT, DELETE | Always safe to retry |
| POST, PATCH | Only with an idempotency key (on by default) |
| 4xx other than 429 | Never: the request needs changing |

Three attempts by default:

```php
SendSeven::factory()->withToken($token)->withRetries(5, baseDelayMilliseconds: 250)->make();
SendSeven::factory()->withToken($token)->withoutRetries()->make();
```

Turning off automatic idempotency keys (`withoutAutomaticIdempotencyKeys()`) also stops POST and PATCH from being retried.

## Rate limits

SendSeven allows **100 standard requests a minute per token**. Everything that shares a token shares that budget: web requests, queue workers and cron jobs. Without coordination, one burst gets everyone else 429s.

Implement `Reshapify\SendSeven\Http\RateLimiter` against a shared store (Redis, a database) and pass it to the factory:

```php
interface RateLimiter
{
    public function acquire(Request $request): void;                     // before each attempt: wait, or throw
    public function observe(Request $request, Response $response): void; // learn from 429s and headers
}
```

A good limiter, as promo-next learned in production:

- keeps headroom below the limit;
- caps bulk sends (campaigns) to part of the budget, so live conversations always have capacity;
- caps each of your customers to a fair share, so one busy customer slows down alone;
- after a 429, pauses every caller until the window resets.

The Laravel package ships one backed by Laravel's cache.

## Timeouts

Configure them on your PSR-18 client (for Guzzle: `new Client(['timeout' => 20])`) and pass it with `withHttpClient()`.
