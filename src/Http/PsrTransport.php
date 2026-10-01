<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Reshapify\SendSeven\Exceptions\TransportFailed;

/**
 * Sends requests through any PSR-18 HTTP client: Guzzle, Symfony
 * HttpClient, or whichever the application already has installed.
 */
final readonly class PsrTransport implements Transport
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    public function __construct(
        private string $baseUri,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function send(Request $request): Response
    {
        $query = $request->queryString();
        $psrRequest = $this->requestFactory->createRequest(
            $request->method->value,
            rtrim($this->baseUri, '/').'/'.ltrim($request->path, '/').($query === '' ? '' : '?'.$query),
        );

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->multipart !== null) {
            $boundary = 'sendseven-'.bin2hex(random_bytes(12));
            $psrRequest = $psrRequest
                ->withHeader('Content-Type', 'multipart/form-data; boundary='.$boundary)
                ->withBody($this->streamFactory->createStream($this->multipartBody($request->multipart, $boundary)));
        } elseif ($request->body !== null) {
            $psrRequest = $psrRequest
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($request->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $clientException) {
            throw TransportFailed::for($request, $clientException);
        }

        $headers = [];

        foreach (array_keys($psrResponse->getHeaders()) as $name) {
            $headers[strtolower($name)] = $psrResponse->getHeaderLine($name);
        }

        return new Response($psrResponse->getStatusCode(), (string) $psrResponse->getBody(), $headers);
    }

    /**
     * @param  array<string, scalar|FilePart|list<scalar|FilePart>|null>  $fields
     */
    private function multipartBody(array $fields, string $boundary): string
    {
        $body = '';

        foreach ($fields as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $body .= "--{$boundary}\r\n";

                if ($item instanceof FilePart) {
                    $filename = addcslashes($item->filename, '"\\');
                    $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\nContent-Type: {$item->contentType}\r\n\r\n{$item->contents}\r\n";
                } else {
                    $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n".(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item)."\r\n";
                }
            }
        }

        return $body."--{$boundary}--\r\n";
    }
}
