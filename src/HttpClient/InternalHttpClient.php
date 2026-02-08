<?php

declare(strict_types=1);

namespace App\HttpClient;

use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * HTTP client that uses internal sub-requests when the URL points to localhost.
 * Fixes deadlock when the PHP built-in server cannot handle concurrent requests to itself.
 */
final class InternalHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $realClient,
        private readonly HttpKernelInterface $kernel,
        private readonly string $internalBaseUrl,
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->isInternalUrl($url)) {
            return $this->realClient->request($method, $url, $options);
        }

        $body = $options['body'] ?? $options['json'] ?? null;
        if (isset($options['json'])) {
            $body = is_string($options['json']) ? $options['json'] : json_encode($options['json'], JSON_THROW_ON_ERROR);
        }

        $request = Request::create($url, $method, [], [], [], [], $body);
        $headers = $options['headers'] ?? [];
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        if ($body && !$request->headers->has('Content-Type') && isset($options['json'])) {
            $request->headers->set('Content-Type', 'application/json');
        }

        $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST, true);

        return new InternalResponse(
            $response->getContent(),
            $response->getStatusCode(),
            $response->headers->all(),
            $url,
            $method,
        );
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $responses = $responses instanceof ResponseInterface ? [$responses] : $responses;
        $timeout ??= 0;

        $generator = (function () use ($responses, $timeout): \Generator {
            foreach ($responses as $response) {
                if ($response instanceof InternalResponse) {
                    $content = $response->getContent(false);
                    yield $response => new DataChunk(0, $content);
                    yield $response => new LastChunk(\strlen($content));
                } else {
                    yield from $this->realClient->stream([$response], $timeout);
                }
            }
        })();

        return new ResponseStream($generator);
    }

    public function withOptions(array $options): static
    {
        return new self(
            $this->realClient->withOptions($options),
            $this->kernel,
            $this->internalBaseUrl,
        );
    }

    private function isInternalUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ('https' === ($parsed['scheme'] ?? '') ? 443 : 80);

        $baseParsed = parse_url($this->internalBaseUrl);
        $baseHost = $baseParsed['host'] ?? 'localhost';
        $basePort = $baseParsed['port'] ?? 80;

        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
            && (int) $port === (int) $basePort;
    }
}
