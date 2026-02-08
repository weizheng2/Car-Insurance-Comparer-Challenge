<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Enum\CarUse;
use App\Exception\ProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Request:
 * driver_age,car_type,car_use (T/S/C, P/C)
 * Response:
 * price,currency
 *
 * @throws ProviderException If the provider does not respond correctly
 */
final class ProviderCService implements ProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly int $timeout = 10,
    ) {}

    public function getName(): string
    {
        return 'provider-c';
    }

    public function requestQuote(QuoteRequest $request): ResponseInterface
    {
        $this->logger->info('Provider C: request received');

        $body = $this->toProviderFormat($request);

        return $this->httpClient->request('POST', $this->url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'text/csv',
                'Accept' => 'text/csv',
            ],
            'timeout' => $this->timeout,
        ]);
    }

    public function parseResponseContent(string $content): float
    {
        $lines = explode("\n", trim($content));
        if (count($lines) < 2) {
            throw ProviderException::invalidResponse($this->getName(), 'Invalid CSV response');
        }

        $headers = str_getcsv($lines[0]);
        $values = str_getcsv($lines[1]);
        $data = array_combine($headers, $values);

        if (!isset($data['price']) || !is_numeric($data['price'])) {
            throw ProviderException::invalidResponse($this->getName(), 'Invalid price');
        }

        return (float) $data['price'];
    }

    private function toProviderFormat(QuoteRequest $request): string
    {
        $carType = match ($request->carType) {
            CarType::TURISMO => 'T',
            CarType::SUV => 'S',
            CarType::COMPACTO => 'C',
        };
        $carUse = match ($request->carUse) {
            CarUse::PRIVATE => 'P',
            CarUse::COMMERCIAL => 'C',
        };

        return "driver_age,car_type,car_use\n{$request->driverAge},{$carType},{$carUse}";
    }

}
