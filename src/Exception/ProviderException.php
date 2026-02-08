<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception when a provider does not return a valid quote.
 */
class ProviderException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function timeout(string $provider, int $timeoutSeconds): self
    {
        return new self(
            provider: $provider,
            message: sprintf(
                'Provider %s request timed out after %d seconds',
                $provider,
                $timeoutSeconds
            ),
            code: 504, // Gateway Timeout
        );
    }

    public static function httpError(string $provider, int $statusCode, string $reason = ''): self
    {
        return new self(
            provider: $provider,
            message: sprintf(
                'Provider %s returned HTTP %d%s',
                $provider,
                $statusCode,
                $reason ? ": $reason" : ''
            ),
            code: $statusCode,
        );
    }

    public static function invalidResponse(string $provider, string $reason): self
    {
        return new self(
            provider: $provider,
            message: sprintf(
                'Provider %s returned invalid response: %s',
                $provider,
                $reason
            ),
            code: 502, // Bad Gateway
        );
    }

    public static function connectionError(string $provider, \Throwable $previous): self
    {
        return new self(
            provider: $provider,
            message: sprintf(
                'Failed to connect to provider %s: %s',
                $provider,
                $previous->getMessage()
            ),
            code: 503, // Service Unavailable
            previous: $previous,
        );
    }

    public function getProviderName(): string
    {
        return $this->provider;
    }
}
