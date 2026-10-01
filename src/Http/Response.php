<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

use JsonException;
use Reshapify\SendSeven\Exceptions\UnexpectedResponse;

/**
 * What SendSeven answered.
 */
final readonly class Response
{
    /**
     * @param  array<string, string>  $headers  lower-cased names
     */
    public function __construct(
        public int $status,
        public string $body = '',
        public array $headers = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR), ['content-type' => 'application/json', ...array_change_key_case($headers)]);
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * The decoded JSON body; null for an empty body (e.g. 204 No Content).
     */
    public function decoded(): mixed
    {
        if (trim($this->body) === '') {
            return null;
        }

        try {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw UnexpectedResponse::because('the body is not valid JSON', $this, $exception);
        }
    }

    /**
     * The decoded body as an object (a JSON object or array).
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        $data = $this->decoded();

        if (! is_array($data)) {
            throw UnexpectedResponse::because('expected a JSON object or array', $this);
        }

        return $data;
    }
}
