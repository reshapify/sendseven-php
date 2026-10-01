<?php

declare(strict_types=1);

use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Exceptions\UnexpectedResponse;
use Reshapify\SendSeven\Support\Attributes;

it('reads typed values', function (): void {
    $attributes = new Attributes(['id' => 'x', 'count' => '3', 'price' => '0.0790', 'on' => true, 'at' => '2026-09-16T05:26:14Z', 'tags' => ['a', 1]]);

    expect($attributes->string('id'))->toBe('x')
        ->and($attributes->int('count'))->toBe(3)
        ->and($attributes->float('price'))->toBe(0.079)
        ->and($attributes->bool('on'))->toBeTrue()
        ->and($attributes->dateTime('at')->format(DATE_ATOM))->toBe('2026-09-16T05:26:14+00:00')
        ->and($attributes->strings('tags'))->toBe(['a', '1']);
});

it('keeps an enum value the SDK does not know yet', function (): void {
    $attributes = new Attributes(['platform' => 'whatsapp', 'other' => 'carrier_pigeon']);

    expect($attributes->enum('platform', ChannelType::class))->toBe(ChannelType::WhatsApp)
        ->and($attributes->enum('other', ChannelType::class))->toBe('carrier_pigeon');
});

it('names the field that broke the contract', function (): void {
    expect(fn (): string => (new Attributes(['message' => []], 'event.data'))->string('id'))
        ->toThrow(UnexpectedResponse::class, 'event.data.id is missing');
    expect(fn (): int => (new Attributes(['count' => 'many'], 'pagination'))->int('count'))
        ->toThrow(UnexpectedResponse::class, 'pagination.count should be an integer, got string');
});
