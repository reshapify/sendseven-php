<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use Reshapify\SendSeven\Exceptions\ValidationFailed;
use Reshapify\SendSeven\Http\Method;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\SendSeven;
use Reshapify\SendSeven\Testing\UnexpectedRequest;

it('answers from a script and records requests', function (): void {
    $fake = SendSeven::fake(['POST /messages' => ['id' => 'msg_1']]);

    $response = $fake->client->request(Method::Post, '/messages', body: ['text' => 'Hi']);

    expect($response->status)->toBe(201)->and($response->data())->toBe(['id' => 'msg_1']);
    $fake->assertSent('POST /messages', fn (Request $request): bool => $request->body === ['text' => 'Hi']);
    $fake->assertSentCount(1);
    $fake->assertNotSent('GET *');
});

it('matches path patterns and answers in turn', function (): void {
    $fake = SendSeven::fake(['GET /contacts/*' => [Response::json(['id' => 'c1']), Response::json(['id' => 'c2'])]]);

    expect($fake->client->request(Method::Get, '/contacts/c1')->data())->toBe(['id' => 'c1'])
        ->and($fake->client->request(Method::Get, '/contacts/c2')->data())->toBe(['id' => 'c2'])
        ->and($fake->client->request(Method::Get, '/contacts/c3')->data())->toBe(['id' => 'c2']);
});

it('turns scripted errors into the real exceptions', function (): void {
    $fake = SendSeven::fake(['POST /messages' => new Response(422, '{"detail":[{"loc":["body","text"],"msg":"Field required","type":"missing"}]}')]);

    expect(fn (): Response => $fake->client->request(Method::Post, '/messages', body: []))->toThrow(ValidationFailed::class, 'body.text: Field required');
});

it('says how to script a request it has no answer for', function (): void {
    expect(fn (): Response => SendSeven::fake()->client->request(Method::Get, '/channels'))
        ->toThrow(UnexpectedRequest::class, "SendSeven::fake(['GET /channels' => [...]])");
});

it('fails assertions with what was actually sent', function (): void {
    $fake = SendSeven::fake(['*' => []]);
    $fake->client->request(Method::Get, '/channels');

    expect(fn () => $fake->assertSent('POST /messages'))->toThrow(ExpectationFailedException::class, 'Sent: GET /channels');
});
