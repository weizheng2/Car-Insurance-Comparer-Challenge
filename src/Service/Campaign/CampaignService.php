<?php
namespace App\Service\Campaign;

final readonly class CampaignService
{
    private const DEFAULT_DISCOUNT_RATE = 0.05;

    public function __construct(
        private bool $campaignActive,
        private float $discountRate = self::DEFAULT_DISCOUNT_RATE,
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
