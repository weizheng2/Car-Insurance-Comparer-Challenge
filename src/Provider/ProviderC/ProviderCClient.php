<?php

declare(strict_types=1);

namespace App\Provider\ProviderC;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Enum\CarUse;
use App\Exception\ProviderException;
use App\Provider\ProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente Provider C (CSV). Incluye normalización de request/response.
 *
 * Formato: driver_age,car_type,car_use (T/S/C, P/C)
 * Respuesta: price,currency
 *
 * @throws ProviderException En errores de conexión, timeout o respuesta inválida
 */
final class ProviderCClient implements ProviderInterface
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

    public function getQuote(QuoteRequest $request): float
    {
        $this->logger->info('Provider C: solicitando presupuesto');

        try {
            $body = $this->toProviderFormat($request);

            $response = $this->httpClient->request('POST', $this->url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'text/csv',
                    'Accept' => 'text/csv',
                ],
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw ProviderException::httpError(
                    $this->getName(),
                    $response->getStatusCode(),
                    $response->getContent(false)
                );
            }

            return $this->parseResponse($response->getContent());
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface $e) {
            throw ProviderException::timeout($this->getName(), $this->timeout);
        } catch (\Throwable $e) {
            throw ProviderException::connectionError($this->getName(), $e);
        }
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

    private function parseResponse(string $response): float
    {
        $lines = explode("\n", trim($response));
        if (count($lines) < 2) {
            throw ProviderException::invalidResponse($this->getName(), 'Respuesta CSV inválida');
        }

        $headers = str_getcsv($lines[0]);
        $values = str_getcsv($lines[1]);
        $data = array_combine($headers, $values);

        if (!isset($data['price']) || !is_numeric($data['price'])) {
            throw ProviderException::invalidResponse($this->getName(), 'Precio inválido');
        }

        return (float) $data['price'];
    }
}
