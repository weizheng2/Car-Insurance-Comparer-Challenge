<?php

declare(strict_types=1);

namespace App\HttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class InternalResponse implements ResponseInterface
{
    private array $info;

    public function __construct(
        private readonly string $content,
        private readonly int $statusCode,
        private readonly array $headers,
        string $url,
        string $method,
    ) {
        $this->info = [
            'http_code' => $statusCode,
            'http_method' => $method,
            'url' => $url,
            'response_headers' => $this->flattenHeaders($headers),
            'canceled' => false,
            'error' => null,
            'redirect_count' => 0,
            'redirect_url' => null,
            'start_time' => 0.0,
            'user_data' => null,
        ];
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        if ($throw && $this->statusCode >= 400) {
            $this->throw();
        }

        $result = [];
        foreach ($this->headers as $name => $values) {
            $result[strtolower($name)] = (array) $values;
        }

        return $result;
    }

    public function getContent(bool $throw = true): string
    {
        if ($throw && $this->statusCode >= 400) {
            $this->throw();
        }

        return $this->content;
    }

    public function toArray(bool $throw = true): array
    {
        $data = json_decode($this->content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \JsonException('Cannot decode JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    public function cancel(): void
    {
        $this->info['canceled'] = true;
    }

    public function getInfo(?string $type = null): mixed
    {
        return null === $type ? $this->info : ($this->info[$type] ?? null);
    }

    private function throw(): never
    {
        throw new \RuntimeException(sprintf('HTTP %d', $this->statusCode));
    }

    private function flattenHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $values) {
            foreach ((array) $values as $value) {
                $result[] = $name . ': ' . $value;
            }
        }

        return $result;
    }
}
