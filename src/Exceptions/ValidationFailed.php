<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 422: one or more fields were missing or invalid. See fieldErrors().
 */
final class ValidationFailed extends ApiException
{
    /**
     * @return list<FieldError>
     */
    public function fieldErrors(): array
    {
        return $this->detail->fieldErrors;
    }

    public function hint(): string
    {
        return 'Fix the fields listed above; fieldErrors() has them one by one.';
    }
}
