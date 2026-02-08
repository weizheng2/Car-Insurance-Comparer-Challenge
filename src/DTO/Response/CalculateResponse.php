<?php

declare(strict_types=1);

namespace App\DTO\Response;

final readonly class CalculateResponse
{
    /**
     * @param Quote[] $quotes
     * @param string[] $failedProviders
     */
    public function __construct(
        public array $quotes,
        public bool $campaignActive = false,
        public float $discountPercentage = 0.0,
        public array $failedProviders = [],
    ) {}

    public function getQuotesSortedByPrice(string $order = 'asc'): array
    {
        $quotes = $this->quotes;
        usort($quotes, static function (Quote $a, Quote $b) use ($order): int {
            $comparison = $a->finalPrice <=> $b->finalPrice;
            return $order === 'desc' ? -$comparison : $comparison;
        });
        return $quotes;
    }

    public function getCheapestProvider(): ?string
    {
        $sorted = $this->getQuotesSortedByPrice();
        return $sorted[0]->provider ?? null;
    }

    public function hasQuotes(): bool
    {
        return count($this->quotes) > 0;
    }

    public function getMessage(): ?string
    {
        if (!$this->hasQuotes() && count($this->failedProviders) > 0) {
            return 'No hay ofertas disponibles.';
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->hasQuotes(),
            'campaign_active' => $this->campaignActive,
            'discount_percentage' => $this->discountPercentage,
            'quotes' => array_map(
                static fn (Quote $q): array => $q->toArray(),
                $this->getQuotesSortedByPrice()
            ),
            'cheapest_provider' => $this->getCheapestProvider(),
            'failed_providers' => $this->failedProviders,
            'message' => $this->getMessage(),
        ];
    }
}
