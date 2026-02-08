<?php

declare(strict_types=1);

namespace App\Service\Campaign;

final readonly class CampaignService
{
    public function __construct(
        private bool $campaignActive,
        private float $discountRate,
    ) {}

    public function isActive(): bool
    {
        return $this->campaignActive;
    }

    public function getDiscountRate(): float
    {
        return $this->isActive() ? $this->discountRate : 0.0;
    }

    public function getDiscountPercentage(): float
    {
        return $this->getDiscountRate() * 100;
    }

    public function applyDiscount(float $originalPrice): array
    {
        $discountRate = $this->getDiscountRate();
        $discountAmount = round($originalPrice * $discountRate, 2);
        $finalPrice = round($originalPrice - $discountAmount, 2);

        return [
            'original' => round($originalPrice, 2),
            'final' => $finalPrice,
            'discount' => $discountAmount,
            'applied' => $this->isActive(),
        ];
    }
}
