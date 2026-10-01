<?php

declare(strict_types=1);

use Reshapify\SendSeven\Client;
use Reshapify\SendSeven\Http\Connector;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\Http\RetryPolicy;
use Reshapify\SendSeven\Http\Sleeper;
use Reshapify\SendSeven\Http\Transport;

/**
 * A payload from tests/Fixtures, decoded.
 *
 * @return array<string, mixed>
 */
function sendSevenFixture(string $path, ?string $key = null): array
{
    $data = json_decode((string) file_get_contents(__DIR__.'/Fixtures/'.$path), true, 512, JSON_THROW_ON_ERROR);

    return $key === null ? $data : $data[$key];
}

/**
 * A transport that answers from a list and records what it was sent.
 */
final class ScriptedTransport implements Transport
{
    /** @var list<Request> */
    public array $sent = [];

    /**
     * @param  list<Response|Throwable>  $answers
     */
    public function __construct(private array $answers) {}

    public function send(Request $request): Response
    {
        $this->sent[] = $request;
        $answer = array_shift($this->answers) ?? throw new LogicException('No scripted answer left for '.$request->describe());

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        return $answer;
    }
}

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $slept = [];

    public function sleep(int $milliseconds): void
    {
        $this->slept[] = $milliseconds;
    }
}

function connector(ScriptedTransport $transport, ?RecordingSleeper $sleeper = null, ?RetryPolicy $retryPolicy = null, ?string $tenantId = null): Connector
{
    return new Connector($transport, 's7_api_test', $tenantId, $retryPolicy ?? new RetryPolicy, sleeper: $sleeper ?? new RecordingSleeper);
}

function clientWith(ScriptedTransport $transport): Client
{
    return new Client(connector($transport));
}
