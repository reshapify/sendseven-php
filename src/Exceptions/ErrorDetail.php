<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Reshapify\SendSeven\Http\Response;

/**
 * SendSeven's error body, which comes in three shapes:
 * {"detail": "text"}, {"detail": {"code": "...", "message": "...", ...}}
 * and, for validation, {"detail": [{"loc": [...], "msg": "...", "type": "..."}]}.
 */
final readonly class ErrorDetail
{
    /**
     * @param  list<FieldError>  $fieldErrors
     * @param  array<array-key, mixed>  $extra  any other keys in an object detail, e.g. "feature"
     */
    public function __construct(
        public string $message,
        public ?string $code = null,
        public array $fieldErrors = [],
        public array $extra = [],
    ) {}

    public static function from(Response $response): self
    {
        $decoded = json_decode($response->body, true);
        $detail = is_array($decoded) ? ($decoded['detail'] ?? $decoded['message'] ?? $decoded['error'] ?? null) : null;

        if (is_string($detail)) {
            return new self($detail);
        }

        if (is_array($detail) && array_is_list($detail)) {
            $errors = array_values(array_filter(array_map(FieldError::fromArray(...), $detail)));

            return new self(
                $errors === [] ? 'The request was invalid' : 'The request was invalid: '.implode('; ', array_map(static fn (FieldError $error): string => (string) $error, $errors)),
                'validation_error',
                $errors,
            );
        }

        if (is_array($detail)) {
            $code = isset($detail['code']) && is_string($detail['code']) ? $detail['code'] : null;
            $message = isset($detail['message']) && is_string($detail['message']) ? $detail['message'] : ($code ?? self::fallback($response));
            unset($detail['code'], $detail['message']);

            return new self($message, $code, extra: $detail);
        }

        return new self(self::fallback($response));
    }

    private static function fallback(Response $response): string
    {
        return $response->status >= 500 ? 'SendSeven had a server error' : 'SendSeven refused the request';
    }
}
