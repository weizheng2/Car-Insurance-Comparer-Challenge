<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\DTO\Request\QuoteRequest;
use App\Exception\ProviderException;
use Symfony\Contracts\HttpClient\ResponseInterface;

interface ProviderInterface
{
    public function getName(): string;

    /**
     * Start a quote request without blocking.
     * Returns the response immediately; the actual HTTP request is sent when streaming.
     */
    public function requestQuote(QuoteRequest $request): ResponseInterface;

    /**
     * Parse the response body into a price.
     * @throws ProviderException If the response format is invalid
     */
    public function parseResponseContent(string $content): float;
}
