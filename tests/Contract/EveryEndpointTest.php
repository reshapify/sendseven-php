<?php

declare(strict_types=1);

use Reshapify\SendSeven\Exceptions\UnexpectedResponse;
use Reshapify\SendSeven\Http\FilePart;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\SendSeven;

/*
 * Every generated method sends the right HTTP method to the right path,
 * with its path parameters in place. The manifest lists each operation and
 * the method that calls it.
 */

/**
 * @return iterable<string, array{0: array<string, mixed>}>
 */
function operations(): iterable
{
    $manifest = json_decode((string) file_get_contents(__DIR__.'/../../openapi/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach ($manifest['operations'] as $operation) {
        yield $operation['operationId'] => [$operation];
    }
}

function placeholder(array $parameter): mixed
{
    $type = ltrim((string) $parameter['type'], '?');

    return match (true) {
        $parameter['in'] === 'path' => 'p-'.$parameter['wire'],
        str_starts_with($type, 'int') => 1,
        str_starts_with($type, 'float') => 1.5,
        $type === 'bool' => true,
        $type === 'array' => [],
        $type === 'FilePart' => FilePart::fromContents('hello', 'hello.txt', 'text/plain'),
        str_starts_with($type, 'DateTimeInterface') => '2026-01-01T00:00:00+00:00',
        default => 'value',
    };
}

it('calls the right endpoint', function (array $operation): void {
    $fake = SendSeven::fake(['*' => []]);
    $arguments = [];

    foreach ($operation['parameters'] as $parameter) {
        if ($parameter['required']) {
            $arguments[$parameter['name']] = placeholder($parameter);
        }
    }

    try {
        $fake->client->{$operation['resource']}()->{$operation['method']}(...$arguments);
    } catch (UnexpectedResponse) {
        // An empty answer doesn't satisfy the response type; only the request matters here.
    }

    [$method, $path] = explode(' ', $operation['http'], 2);
    $expected = preg_replace_callback('/\{([^}]+)\}/', static fn (array $match): string => rawurlencode('p-'.$match[1]), $path);

    $fake->assertSent("{$method} {$expected}", static fn (Request $request): bool => $request->path === $expected);
})->with(operations());
