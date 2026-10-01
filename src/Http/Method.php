<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

enum Method: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    /**
     * Whether repeating the request has the same effect as sending it once,
     * so it can be retried without an idempotency key.
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::Get, self::Put, self::Delete => true,
            self::Post, self::Patch => false,
        };
    }
}
