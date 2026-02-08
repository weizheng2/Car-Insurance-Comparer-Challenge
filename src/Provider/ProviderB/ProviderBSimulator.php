<?php

namespace App\Provider\ProviderB;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Enum\CarUse;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Simulador de Provider B (hardcoded).
 *
 * Cálculo de precios simulado según especificación:
 * - Base: 250€
 * - Edad 18-29: +50€ | 30-59: +20€ | 60+: +100€
 * - Turismo: +30€ | SUV: +200€ | Compacto: +0€
 * - Sin ajuste por uso comercial
 */
#[Route('/api/provider-b')]
final class ProviderBSimulator extends AbstractController
{
    private const BASE = 250.0;
    private const AGE_18_29 = 50.0;
    private const AGE_30_59 = 20.0;
    private const AGE_60_PLUS = 100.0;
    private const VEHICLE_TURISMO = 30.0;
    private const VEHICLE_SUV = 200.0;
    private const VEHICLE_COMPACTO = 0.0;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/quote', name: 'provider_b_quote', methods: ['POST'])]
    public function quote(Request $request): Response
    {
        $this->logger->info('Provider B: solicitud recibida');

        sleep(5); // Simula latencia ~5s

        if (random_int(1, 100) === 1) {
            $this->logger->warning('Provider B: simulación de 1% (timeout)');
            // sleep(60); // Descomentar para simular timeout real
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
