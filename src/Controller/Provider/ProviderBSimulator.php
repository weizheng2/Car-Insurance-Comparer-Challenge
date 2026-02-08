<?php

declare(strict_types=1);

namespace App\Controller\Provider;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Simulated price calculation according to specification:
 * - Base: 250€
 * - Edad 18-29: +50€ | 30-59: +20€ | 60+: +100€
 * - Turismo: +30€ | SUV: +200€ | Compacto: +0€
 * - No commercial use adjustment
 */
#[Route('/api/provider-b')]
final class ProviderBSimulator extends AbstractController
{
    private const SLEEP_TIME = 5;
    private const TIMEOUT_PROBABILITY_PERCENT = 1; 

    private const BASE = 250.0;

    private const AGE_18_29 = 50.0;
    private const AGE_30_59 = 20.0;
    private const AGE_60_PLUS = 100.0;

    private const VEHICLE_TURISMO = 30.0;
    private const VEHICLE_SUV = 200.0;
    private const VEHICLE_COMPACTO = 0.0;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $enableProviderErrors,
    ) {}

    #[Route('/quote', name: 'provider_b_quote', methods: ['POST'])]
    public function quote(Request $request): Response
    {
        $this->logger->info('Provider B: request received');

        if ($this->enableProviderErrors) {
            sleep(self::SLEEP_TIME);
        }

        if ($this->enableProviderErrors && random_int(1, 100) <= self::TIMEOUT_PROBABILITY_PERCENT) {
            $this->logger->warning('Provider B: simulate 1% (timeout)');
            return new Response("error\nInternal server error", Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'application/xml']);
        }

        try {
            $data = $this->parseXmlRequest($request->getContent());
            $quoteRequest = QuoteRequest::fromArray([
                'driver_age' => $data['EdadConductor'],
                'car_type' => $this->mapTipoCoche($data['TipoCoche'] ?? ''),
                'car_use' => $this->mapUsoCoche($data['UsoCoche'] ?? ''),
            ]);
            $price = $this->calculatePrice($quoteRequest);

            $xml = new \SimpleXMLElement('<RespuestaCotizacion/>');
            $xml->addChild('Precio', number_format($price, 1, '.', ''));
            $xml->addChild('Moneda', 'EUR');

            return new Response($xml->asXML(), Response::HTTP_OK, ['Content-Type' => 'application/xml']);
        } catch (\Throwable $e) {
            $this->logger->error('Provider B: error', ['error' => $e->getMessage()]);
            return new Response(
                '<Error><Mensaje>' . htmlspecialchars($e->getMessage()) . '</Mensaje></Error>',
                Response::HTTP_BAD_REQUEST,
                ['Content-Type' => 'application/xml']
            );
        }
    }

    private function parseXmlRequest(string $content): array
    {
        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            throw new \InvalidArgumentException('XML inválido');
        }

        return [
            'EdadConductor' => isset($xml->EdadConductor) ? (int) $xml->EdadConductor : null,
            'TipoCoche' => isset($xml->TipoCoche) ? (string) $xml->TipoCoche : null,
            'UsoCoche' => isset($xml->UsoCoche) ? (string) $xml->UsoCoche : null,
        ];
    }

    private function mapTipoCoche(string $tipo): string
    {
        return match (strtolower($tipo)) {
            'turismo' => 'turismo',
            'suv' => 'suv',
            'compacto' => 'compacto',
            default => $tipo,
        };
    }

    private function mapUsoCoche(string $uso): string
    {
        return match (strtolower($uso)) {
            'privado' => 'private',
            'comercial' => 'commercial',
            default => $uso,
        };
    }

    private function calculatePrice(QuoteRequest $request): float
    {
        $price = self::BASE;

        $price += match (true) {
            $request->driverAge >= 18 && $request->driverAge <= 29 => self::AGE_18_29,
            $request->driverAge >= 30 && $request->driverAge <= 59 => self::AGE_30_59,
            default => self::AGE_60_PLUS,
        };

        $price += match ($request->carType) {
            CarType::TURISMO => self::VEHICLE_TURISMO,
            CarType::SUV => self::VEHICLE_SUV,
            CarType::COMPACTO => self::VEHICLE_COMPACTO,
        };

        return round($price, 2);
    }
}
