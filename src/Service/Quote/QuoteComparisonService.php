<?php
namespace App\Service\Quote;

use App\DTO\Request\QuoteRequest;
use App\DTO\Response\CalculateResponse;
use App\DTO\Response\Quote;
use App\Exception\ProviderException;
use App\Provider\ProviderInterface;
use App\Service\Campaign\CampaignService;
use Psr\Log\LoggerInterface;

/**
 * Servicio principal que orquesta la comparación de presupuestos.
 *
 * Responsabilidades:
 * - Solicitar datos a los proveedores
 * - Manejar errores de forma degradada
 * - Aplicar campaña cuando esté activa
 * - Ordenar y devolver la respuesta
 */
final class QuoteComparisonService
{
    /**
     * @param iterable<ProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly CampaignService $campaignService,
        private readonly LoggerInterface $logger,
    ) {}

    public function compare(QuoteRequest $request): CalculateResponse
    {
        $this->logger->info('Comparando presupuestos', [
            'driver_age' => $request->driverAge,
            'car_type' => $request->carType->value,
            'car_use' => $request->carUse->value,
        ]);

        $quotes = [];
        $failedProviders = [];
        $discountRate = $this->campaignService->getDiscountRate();

        foreach ($this->providers as $provider) {
            try {
                $price = $provider->getQuote($request);

                $discountAmount = round($price * $discountRate, 2);
                $finalPrice = round($price - $discountAmount, 2);

                $quotes[] = Quote::create(
                    provider: $provider->getName(),
                    originalPrice: $price,
                    finalPrice: $finalPrice,
                    discountAmount: $discountAmount,
                    hasDiscount: $discountRate > 0,
                );

                $this->logger->info('Presupuesto recibido', [
                    'provider' => $provider->getName(),
                    'price' => $price,
                ]);
            } catch (ProviderException $e) {
                $this->logger->warning('Proveedor falló', [
                    'provider' => $e->provider,
                    'error' => $e->getMessage(),
                ]);
                $failedProviders[] = $e->provider;
            }
        }

        usort($quotes, static fn (Quote $a, Quote $b): int => $a->finalPrice <=> $b->finalPrice);

        return new CalculateResponse(
            quotes: $quotes,
            campaignActive: $this->campaignService->isActive(),
            discountPercentage: $this->campaignService->getDiscountPercentage(),
            failedProviders: $failedProviders,
        );
    }
}
