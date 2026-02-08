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
 *  <SolicitudCotizacion>
 *     <EdadConductor>30</EdadConductor>
 *     <TipoCoche>turismo</TipoCoche>
 *     <UsoCoche>privado</UsoCoche>
 *     <ConductorOcasional>NO</ConductorOcasional>
 * </SolicitudCotizacion>
 *
 * Response: 
 * <RespuestaCotizacion>
 *     <Precio>310.0</Precio>
 *     <Moneda>EUR</Moneda>
 * </RespuestaCotizacion>
 *
 * @throws ProviderException If the provider does not respond correctly
 */
final class ProviderBService implements ProviderInterface
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

    public function requestQuote(QuoteRequest $request): ResponseInterface
    {
        $this->logger->info('Provider B: request received');

        $body = $this->toProviderFormat($request);

        return $this->httpClient->request('POST', $this->url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ],
            'timeout' => $this->timeout,
        ]);
    }

    public function parseResponseContent(string $content): float
    {
        $xml = @simplexml_load_string($content);

        if ($xml === false || !isset($xml->Precio)) {
            throw ProviderException::invalidResponse($this->getName(), 'Respuesta XML inválida');
        }

        $price = (string) $xml->Precio;
        if (!is_numeric($price)) {
            throw ProviderException::invalidResponse($this->getName(), 'Precio inválido');
        }

        return (float) $price;
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

}
