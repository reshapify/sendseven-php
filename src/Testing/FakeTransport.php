<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Testing;

use Closure;
use Reshapify\SendSeven\Http\Method;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\Http\Transport;

/**
 * @internal use SendSeven::fake()
 */
final class FakeTransport implements Transport
{
    /** @var array<string, list<mixed>> */
    private array $queues = [];

    /** @var list<Request> */
    private array $requests = [];

    public function queue(string $pattern, mixed $response): void
    {
        $responses = is_array($response) && array_is_list($response) && $response !== [] && ! $this->isJsonList($response) ? $response : [$response];

        foreach ($responses as $item) {
            $this->queues[$pattern][] = $item;
        }
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        foreach ($this->queues as $pattern => $responses) {
            if ($responses === [] || ! self::matches($pattern, $request)) {
                continue;
            }

            // The last scripted answer for a pattern keeps answering.
            $response = count($responses) > 1 ? array_shift($this->queues[$pattern]) : $responses[0];

            return self::toResponse($response, $request);
        }

        throw UnexpectedRequest::for($request, array_keys($this->queues));
    }

    /**
     * @return list<Request>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public static function matches(string $pattern, Request $request): bool
    {
        if ($pattern === '*') {
            return true;
        }

        [$method, $path] = str_contains($pattern, ' ') ? explode(' ', $pattern, 2) : ['*', $pattern];

        if ($method !== '*' && strcasecmp($method, $request->method->value) !== 0) {
            return false;
        }

        $regex = '#^/?'.str_replace('\*', '.*', preg_quote(ltrim($path, '/'), '#')).'$#';

        return preg_match($regex, ltrim($request->path, '/')) === 1;
    }

    private static function toResponse(mixed $response, Request $request): Response
    {
        return match (true) {
            $response instanceof Response => $response,
            $response instanceof Closure => self::toResponse($response($request), $request),
            is_array($response) => Response::json($response, $request->method === Method::Post ? 201 : 200),
            $response === null => new Response(204),
            default => Response::json(['value' => $response]),
        };
    }

    /**
     * A list response body (e.g. GET /channels returns a JSON array), as
     * opposed to a list of scripted responses.
     *
     * @param  list<mixed>  $value
     */
    private function isJsonList(array $value): bool
    {
        foreach ($value as $item) {
            if ($item instanceof Response || $item instanceof Closure) {
                return false;
            }
        }

        return true;
    }
}
