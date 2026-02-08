<?php

declare(strict_types=1);

namespace App\DTO\Response;

final readonly class Quote
{
    public function __construct(
        public string $provider,
        public string $providerName,
        public float $originalPrice,
        public float $finalPrice,
        public float $discountAmount = 0.0,
        public bool $hasDiscount = false,
        public string $currency = 'EUR',
    ) {}

    public static function create(
        string $provider,
        float $originalPrice,
        float $finalPrice,
        float $discountAmount = 0.0,
        bool $hasDiscount = false,
        string $providerName = '',
    ): self {
        $name = $providerName ?: ucfirst(str_replace('-', ' ', $provider));
        return new self(
            provider: $provider,
            providerName: $name,
            originalPrice: $originalPrice,
            finalPrice: $finalPrice,
            discountAmount: $discountAmount,
            hasDiscount: $hasDiscount,
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_name' => $this->providerName,
            'original_price' => $this->originalPrice,
            'final_price' => $this->finalPrice,
            'discount_amount' => $this->discountAmount,
            'has_discount' => $this->hasDiscount,
            'currency' => $this->currency,
        ];
    }
}
