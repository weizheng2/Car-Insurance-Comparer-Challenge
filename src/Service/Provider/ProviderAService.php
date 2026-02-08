<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Exception\ProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Request: {
 *     "driver_age": 30,
 *     "car_form": "sedan",
 *     "car_use": "private"
 * }
 * Response: {
 *     "price": "295 EUR"
 * }
 * @throws ProviderException If the provider does not respond correctly
 */
final class ProviderAService implements ProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly int $timeout = 10,
    ) {}

    public function getName(): string
    {
        return 'provider-a';
    }

    public function requestQuote(QuoteRequest $request): ResponseInterface
    {
        $this->logger->info('Provider A: request received');

        $body = $this->toProviderFormat($request);

        return $this->httpClient->request('POST', $this->url, [
            'json' => $body,
            'timeout' => $this->timeout,
        ]);
    }

    public function parseResponseContent(string $content): float
    {
        $data = json_decode($content, true);

        if (!isset($data['price'])) {
            throw ProviderException::invalidResponse($this->getName(), '"price" field is missing');
        }

        $numeric = preg_replace('/[^0-9.]/', '', $data['price']);
        if (!is_numeric($numeric)) {
            throw ProviderException::invalidResponse($this->getName(), 'Invalid price');
        }

        return (float) $numeric;
    }

    private function toProviderFormat(QuoteRequest $request): array
    {
        return [
            'driver_age' => $request->driverAge,
            'car_form' => match ($request->carType) {
                CarType::TURISMO, CarType::COMPACTO => 'compact',
                CarType::SUV => 'suv',
            },
            'car_use' => $request->carUse->value,
        ];
    }

}
