<?php

declare(strict_types=1);

namespace App\Controller\Provider;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** *
 * - Base: 217€
 * - Age 18-24: +70€ | 25-55: +0€ | 56+: +90€
 * - SUV: +100€ | Compact: +10€
 * - Commercial use: +15%
 */
#[Route('/api/provider-a')]
final class ProviderASimulator extends AbstractController
{
    private const ERROR_RATE = 10;
    private const SLEEP_TIME = 2;

    private const BASE = 217.0;
    
    private const AGE_18_24 = 70.0;
    private const AGE_56_PLUS = 90.0;

    private const VEHICLE_SUV = 100.0;
    private const VEHICLE_COMPACT = 10.0;

    private const COMMERCIAL_MULTIPLIER = 1.15;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $enableProviderErrors,
    ) {}

    #[Route('/quote', name: 'provider_a_quote', methods: ['POST'])]
    public function quote(Request $request): Response
    {
        $this->logger->info('Provider A: request received');

        if ($this->enableProviderErrors) {
            sleep(self::SLEEP_TIME);
        }

        if ($this->enableProviderErrors && random_int(1, 100) <= self::ERROR_RATE) {
            $this->logger->warning('Provider A: simulate error (10%)');
            return new JsonResponse(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $quoteRequest = $this->parseRequest($data);
            $price = $this->calculatePrice($quoteRequest);

            return new JsonResponse([
                'price' => sprintf('%s EUR', number_format($price, 0, '.', '')),
            ]);
        } catch (\JsonException $e) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function parseRequest(array $data): QuoteRequest
    {
        return QuoteRequest::fromArray([
            'driver_age' => $data['driver_age'] ?? null,
            'car_type' => $this->mapCarForm($data['car_form'] ?? ''),
            'car_use' => $data['car_use'] ?? null,
        ]);
    }

    private function mapCarForm(string $carForm): string
    {
        return match (strtolower($carForm)) {
            'sedan' => 'turismo',
            'compact' => 'compacto',
            'suv' => 'suv',
            default => $carForm,
        };
    }

    private function calculatePrice(QuoteRequest $request): float
    {
        $price = self::BASE;

        $price += match (true) {
            $request->driverAge >= 18 && $request->driverAge <= 24 => self::AGE_18_24,
            $request->driverAge >= 25 && $request->driverAge <= 55 => 0.0,
            default => self::AGE_56_PLUS,
        };

        $price += match ($request->carType) {
            CarType::SUV => self::VEHICLE_SUV,
            CarType::TURISMO, CarType::COMPACTO => self::VEHICLE_COMPACT,
        };

        if ($request->carUse->isCommercial()) {
            $price *= self::COMMERCIAL_MULTIPLIER;
        }

        return round($price, 2);
    }
}
