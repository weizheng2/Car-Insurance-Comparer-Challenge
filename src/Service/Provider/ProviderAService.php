<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Exception\ProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Servicio Provider A (JSON). Incluye normalización de request/response.
 *
 * Formato: driver_age, car_form (compact/suv), car_use (private/commercial)
 * Respuesta: {"price": "295 EUR"}
 *
 * @throws ProviderException En errores de conexión, timeout o respuesta inválida
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

    public function getQuote(QuoteRequest $request): float
    {
        $this->logger->info('Provider A: solicitando presupuesto');

        try {
            $body = $this->toProviderFormat($request);

            $response = $this->httpClient->request('POST', $this->url, [
                'json' => $body,
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

    private function parseResponse(string $response): float
    {
        $data = json_decode($response, true);

        if (!isset($data['price'])) {
            throw ProviderException::invalidResponse($this->getName(), 'Falta campo "price"');
        }

        $numeric = preg_replace('/[^0-9.]/', '', $data['price']);
        if (!is_numeric($numeric)) {
            throw ProviderException::invalidResponse($this->getName(), 'Precio inválido');
        }

        return (float) $numeric;
    }
}
