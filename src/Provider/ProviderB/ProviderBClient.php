<?php

declare(strict_types=1);

namespace App\Provider\ProviderB;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Enum\CarUse;
use App\Exception\ProviderException;
use App\Provider\ProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente Provider B (XML). Incluye normalización de request/response.
 *
 * Formato XML: EdadConductor, TipoCoche, UsoCoche
 * Respuesta: <RespuestaCotizacion><Precio>310.0</Precio><Moneda>EUR</Moneda></RespuestaCotizacion>
 *
 * @throws ProviderException En errores de conexión, timeout o respuesta inválida
 */
final class ProviderBClient implements ProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $url,
        private readonly int $timeout = 10,
    ) {}

    public function getName(): string
    {
        return 'provider-b';
    }

    public function getQuote(QuoteRequest $request): float
    {
        $this->logger->info('Provider B: solicitando presupuesto');

        try {
            $body = $this->toProviderFormat($request);

            $response = $this->httpClient->request('POST', $this->url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/xml',
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
        $xml = new \SimpleXMLElement('<SolicitudCotizacion/>');
        $xml->addChild('EdadConductor', (string) $request->driverAge);
        $xml->addChild('TipoCoche', match ($request->carType) {
            CarType::TURISMO => 'turismo',
            CarType::SUV => 'SUV',
            CarType::COMPACTO => 'compacto',
        });
        $xml->addChild('UsoCoche', match ($request->carUse) {
            CarUse::PRIVATE => 'privado',
            CarUse::COMMERCIAL => 'comercial',
        });
        $xml->addChild('ConductorOcasional', 'NO');

        $dom = dom_import_simplexml($xml)->ownerDocument;
        return $dom->saveXML($dom->documentElement);
    }

    private function parseResponse(string $response): float
    {
        $xml = @simplexml_load_string($response);

        if ($xml === false || !isset($xml->Precio)) {
            throw ProviderException::invalidResponse($this->getName(), 'Respuesta XML inválida');
        }

        $price = (string) $xml->Precio;
        if (!is_numeric($price)) {
            throw ProviderException::invalidResponse($this->getName(), 'Precio inválido');
        }

        return (float) $price;
    }
}
