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

it('names the field whose type broke the contract', function (): void {
    expect(fn (): int => (new Attributes(['count' => 'many'], 'pagination'))->int('count'))
        ->toThrow(UnexpectedResponse::class, 'pagination.count should be an integer, got string');
});

it('reads a required field the response left out as an empty value', function (): void {
    $attributes = new Attributes([]);

    expect($attributes->string('name'))->toBe('')
        ->and($attributes->int('total'))->toBe(0)
        ->and($attributes->float('price'))->toBe(0.0)
        ->and($attributes->bool('is_active'))->toBeFalse()
        ->and($attributes->dateTime('created_at')->getTimestamp())->toBe(0)
        ->and($attributes->enum('platform', ChannelType::class))->toBe('')
        ->and($attributes->strings('scopes'))->toBe([])
        ->and($attributes->object('owner', fn (array $data, string $path): array => [$data, $path]))->toBe([[], 'response.owner']);
});
