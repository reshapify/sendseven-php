<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Reshapify\SendSeven\Http\FilePart;
use Reshapify\SendSeven\Http\Method;
use Reshapify\SendSeven\Http\PsrTransport;
use Reshapify\SendSeven\Http\Request;

final class CapturingClient implements ClientInterface
{
    public ?RequestInterface $request = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new PsrResponse(201, ['X-Request-ID' => 'req_9'], '{"id":"msg_1"}');
    }
}

function transport(CapturingClient $client): PsrTransport
{
    return new PsrTransport('https://api.sendseven.com/api/v1/', $client, new HttpFactory, new HttpFactory);
}

it('sends JSON to the full URL and reads the response', function (): void {
    $client = new CapturingClient;

    $response = transport($client)->send(new Request(Method::Post, '/messages', ['dry_run' => true], ['text' => 'Grüße / ok'], ['Authorization' => 'Bearer t']));

    expect((string) $client->request?->getUri())->toBe('https://api.sendseven.com/api/v1/messages?dry_run=true')
        ->and($client->request?->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($client->request?->getHeaderLine('Authorization'))->toBe('Bearer t')
        ->and((string) $client->request?->getBody())->toBe('{"text":"Grüße / ok"}')
        ->and($response->status)->toBe(201)
        ->and($response->header('X-Request-ID'))->toBe('req_9')
        ->and($response->data())->toBe(['id' => 'msg_1']);
});

it('uploads files as multipart form data', function (): void {
    $client = new CapturingClient;

    transport($client)->send(new Request(Method::Post, '/attachments/upload', multipart: ['file' => FilePart::fromContents('%PDF-1.7', 'invoice.pdf', 'application/pdf'), 'public' => true]));

    $contentType = (string) $client->request?->getHeaderLine('Content-Type');
    $body = (string) $client->request?->getBody();

    expect($contentType)->toStartWith('multipart/form-data; boundary=')
        ->and($body)->toContain("Content-Disposition: form-data; name=\"file\"; filename=\"invoice.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.7\r\n")
        ->and($body)->toContain("Content-Disposition: form-data; name=\"public\"\r\n\r\ntrue\r\n")
        ->and($body)->toEndWith('--'.substr($contentType, strlen('multipart/form-data; boundary=')).'--'."\r\n");
});
