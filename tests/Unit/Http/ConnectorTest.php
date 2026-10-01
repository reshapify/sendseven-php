<?php

declare(strict_types=1);

use Reshapify\SendSeven\Exceptions\RateLimited;
use Reshapify\SendSeven\Exceptions\ServerError;
use Reshapify\SendSeven\Exceptions\TransportFailed;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\Http\RetryPolicy;
use Reshapify\SendSeven\SendSeven;

it('authenticates every request and identifies the SDK', function () {
    $transport = new ScriptedTransport([Response::json(['ok' => true])]);

    connector($transport)->send(Request::get('/tenants/me'));

    expect($transport->sent[0]->headers)
        ->toMatchArray(['Authorization' => 'Bearer s7_api_test', 'Accept' => 'application/json'])
        ->and($transport->sent[0]->header('User-Agent'))->toBe('reshapify/sendseven-php/'.SendSeven::VERSION.' PHP/'.PHP_VERSION);
});

it('adds an idempotency key to writes, never to reads, and keeps one you set', function () {
    $transport = new ScriptedTransport([Response::json([]), Response::json([]), Response::json([])]);
    $connector = connector($transport);

    $connector->send(Request::post('/messages', ['text' => 'Hi']));
    $connector->send(Request::get('/messages'));
    $connector->send(Request::post('/messages', ['text' => 'Hi'])->withHeader('Idempotency-Key', 'order-42'));

    expect($transport->sent[0]->header('Idempotency-Key'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($transport->sent[1]->header('Idempotency-Key'))->toBeNull()
        ->and($transport->sent[2]->header('Idempotency-Key'))->toBe('order-42');
});

it('acts on another tenant through X-Tenant-ID', function () {
    $transport = new ScriptedTransport([Response::json([])]);

    connector($transport)->forTenant('tenant_acme')->send(Request::get('/contacts'));

    expect($transport->sent[0]->header('X-Tenant-ID'))->toBe('tenant_acme');
});

it('retries rate limits and server errors with the same idempotency key, honouring Retry-After', function () {
    $sleeper = new RecordingSleeper;
    $transport = new ScriptedTransport([
        new Response(429, '{"detail":"Too many requests"}', ['retry-after' => '2']),
        new Response(503, ''),
        Response::json(['id' => 'msg_1'], 201),
    ]);

    $response = connector($transport, $sleeper)->send(Request::post('/messages', ['text' => 'Hi']));

    expect($response->status)->toBe(201)
        ->and($transport->sent)->toHaveCount(3)
        ->and(array_unique(array_map(fn (Request $request) => $request->header('Idempotency-Key'), $transport->sent)))->toHaveCount(1)
        ->and($sleeper->slept[0])->toBe(2000);
});

it('gives up after the last attempt and throws what SendSeven said', function () {
    $transport = new ScriptedTransport(array_fill(0, 3, new Response(500, '{"detail":"boom"}')));

    expect(fn () => connector($transport)->send(Request::get('/channels')))->toThrow(ServerError::class, 'boom (GET /channels, HTTP 500)');
    expect($transport->sent)->toHaveCount(3);
});

it('never retries a write without an idempotency key', function () {
    $transport = new ScriptedTransport([new Response(503, '')]);
    $connector = new Reshapify\SendSeven\Http\Connector($transport, 'token', automaticIdempotencyKeys: false, sleeper: new RecordingSleeper);

    expect(fn () => $connector->send(Request::post('/messages', ['text' => 'Hi'])))->toThrow(ServerError::class);
    expect($transport->sent)->toHaveCount(1);
});

it('retries a request that never reached SendSeven', function () {
    $failure = TransportFailed::for(Request::get('/channels'), new RuntimeException('Connection refused'));
    $transport = new ScriptedTransport([$failure, Response::json([])]);

    expect(connector($transport)->send(Request::get('/channels'))->status)->toBe(200);
});

it('throws RateLimited with the wait once retries are exhausted', function () {
    $transport = new ScriptedTransport([new Response(429, '{"detail":"slow down"}', ['retry-after' => '30'])]);

    try {
        connector($transport, retryPolicy: RetryPolicy::none())->send(Request::get('/contacts'));
        $this->fail('Expected RateLimited');
    } catch (RateLimited $exception) {
        expect($exception->retryAfter())->toBe(30)
            ->and($exception->getMessage())->toContain('Retry after 30 seconds');
    }
});
