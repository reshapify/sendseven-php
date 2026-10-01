<?php

declare(strict_types=1);

use Reshapify\SendSeven\Exceptions\UnexpectedResponse;
use Reshapify\SendSeven\Http\Response;

it('reads a success with no body as an empty object', function (): void {
    expect((new Response(204))->data())->toBe([])
        ->and((new Response(200, ' '))->data())->toBe([]);
});

it('still rejects a body that is not a JSON object or list', function (): void {
    expect(fn (): array => (new Response(200, '"ok"'))->data())->toThrow(UnexpectedResponse::class, 'expected a JSON object or array');
    expect(fn (): array => (new Response(200, '<html>'))->data())->toThrow(UnexpectedResponse::class, 'not valid JSON');
});
