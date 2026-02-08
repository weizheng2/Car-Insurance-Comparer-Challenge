<?php

declare(strict_types=1);

namespace App\Controller\Provider;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Enum\CarUse;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Simulador de Provider C (endpoint HTTP).
 *
 * Cálculo simulado: Base 195€, age brackets, vehicle adj, comercial +20%
 */
#[Route('/api/provider-c')]
final class ProviderCSimulator extends AbstractController
{
    private const float BASE = 195.0;
    private const float AGE_18_25 = 80.0;
    private const float AGE_26_45 = 10.0;
    private const float AGE_46_65 = 30.0;
    private const float AGE_66_PLUS = 120.0;
    private const float VEHICLE_TURISMO = 25.0;
    private const float VEHICLE_SUV = 150.0;
    private const float VEHICLE_COMPACTO = 5.0;
    private const float COMMERCIAL_MULTIPLIER = 1.20;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/quote', name: 'provider_c_quote', methods: ['POST'])]
    public function quote(Request $request): Response
    {
        $this->logger->info('Provider C: solicitud recibida');

        sleep(3);

        if (random_int(1, 20) === 1) {
            $this->logger->warning('Provider C: simulación de error (5%)');
            return new Response("error\nInternal server error", Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'text/csv']);
        }

        try {
            $data = $this->parseCsvRequest($request->getContent());
            $quoteRequest = QuoteRequest::fromArray([
                'driver_age' => (int) $data['driver_age'],
                'car_type' => $this->mapCarType($data['car_type']),
                'car_use' => $this->mapCarUse($data['car_use']),
            ]);
            $price = $this->calculatePrice($quoteRequest);

            return new Response(
                sprintf("price,currency\n%.2f,EUR", $price),
                Response::HTTP_OK,
                ['Content-Type' => 'text/csv']
            );
        } catch (\Throwable $e) {
            return new Response("error\n" . $e->getMessage(), Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/csv']);
        }
    }

    private function parseCsvRequest(string $content): array
    {
        $lines = explode("\n", trim($content));
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('CSV inválido');
        }

        $headers = str_getcsv($lines[0]);
        $values = str_getcsv($lines[1]);
        return array_combine($headers, $values) ?: [];
    }

    private function mapCarType(string $code): string
    {
        return match (strtoupper($code)) {
            'T' => 'turismo',
            'S' => 'suv',
            'C' => 'compacto',
            default => $code,
        };
    }

    private function mapCarUse(string $code): string
    {
        return match (strtoupper($code)) {
            'P' => 'private',
            'C' => 'commercial',
            default => $code,
        };
    }

    private function calculatePrice(QuoteRequest $request): float
    {
        $price = self::BASE;

        $price += match (true) {
            $request->driverAge >= 18 && $request->driverAge <= 25 => self::AGE_18_25,
            $request->driverAge >= 26 && $request->driverAge <= 45 => self::AGE_26_45,
            $request->driverAge >= 46 && $request->driverAge <= 65 => self::AGE_46_65,
            default => self::AGE_66_PLUS,
        };

        $price += match ($request->carType) {
            CarType::TURISMO => self::VEHICLE_TURISMO,
            CarType::SUV => self::VEHICLE_SUV,
            CarType::COMPACTO => self::VEHICLE_COMPACTO,
        };

        if ($request->carUse->isCommercial()) {
            $price *= self::COMMERCIAL_MULTIPLIER;
        }

        return round($price, 2);
    }
}
