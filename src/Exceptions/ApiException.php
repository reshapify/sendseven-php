<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use RuntimeException;

/**
 * SendSeven answered with an error. The message names the endpoint, the
 * status and what to do about it; the details are on the properties.
 */
abstract class ApiException extends RuntimeException implements SendSevenException
{
    public readonly int $status;

    /** SendSeven's machine-readable error code, e.g. "feature_disabled", when it gives one. */
    public readonly ?string $errorCode;

    /** SendSeven's X-Request-ID, worth quoting to their support. */
    public readonly ?string $requestId;

    final public function __construct(
        public readonly Request $request,
        public readonly Response $response,
        public readonly ErrorDetail $detail,
    ) {
        $this->status = $response->status;
        $this->errorCode = $detail->code;
        $this->requestId = $response->header('x-request-id');

        parent::__construct(sprintf(
            '%s (%s, HTTP %d%s). %s',
            rtrim($detail->message, '.'),
            $request->describe(),
            $response->status,
            $this->requestId === null ? '' : ', request '.$this->requestId,
            $this->hint(),
        ), $response->status);
    }

    /**
     * What to do next, in one sentence.
     */
    abstract public function hint(): string;
}
