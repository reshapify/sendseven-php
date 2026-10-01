<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

/**
 * One call to the SendSeven API, before it goes over the wire.
 */
final readonly class Request
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $query  null values are left out
     * @param  array<array-key, mixed>|null  $body  sent as JSON
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public Method $method,
        public string $path,
        public array $query = [],
        public ?array $body = null,
        public array $headers = [],
    ) {}

    /**
     * @param  array<string, scalar|list<scalar>|null>  $query
     */
    public static function get(string $path, array $query = []): self
    {
        return new self(Method::Get, $path, $query);
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     * @param  array<string, scalar|list<scalar>|null>  $query
     */
    public static function post(string $path, ?array $body = null, array $query = []): self
    {
        return new self(Method::Post, $path, $query, $body);
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     */
    public static function put(string $path, ?array $body = null): self
    {
        return new self(Method::Put, $path, body: $body);
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     */
    public static function patch(string $path, ?array $body = null): self
    {
        return new self(Method::Patch, $path, body: $body);
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     * @param  array<string, scalar|list<scalar>|null>  $query
     */
    public static function delete(string $path, ?array $body = null, array $query = []): self
    {
        return new self(Method::Delete, $path, $query, $body);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->method, $this->path, $this->query, $this->body, [...$this->headers, $name => $value]);
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * "POST /messages", as used in errors and fake assertions.
     */
    public function describe(): string
    {
        return $this->method->value.' '.$this->path;
    }

    /**
     * The query string, with null values left out and lists repeated
     * (tag_id=a&tag_id=b), as SendSeven expects.
     */
    public function queryString(): string
    {
        $pairs = [];

        foreach ($this->query as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $pairs[] = rawurlencode($name).'='.rawurlencode(self::stringify($item));
            }
        }

        return implode('&', $pairs);
    }

    private static function stringify(int|float|string|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
