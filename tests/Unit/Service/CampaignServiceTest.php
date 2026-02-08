<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Campaign\CampaignService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for campaign discount application.
 */
final class CampaignServiceTest extends TestCase
{
    public function testDiscountAppliedWhenCampaignActive(): void
    {
        $service = new CampaignService(campaignActive: true, discountRate: 0.05);

        $this->assertTrue($service->isActive());
        $this->assertSame(0.05, $service->getDiscountRate());
        $this->assertSame(5.0, $service->getDiscountPercentage());

        $result = $service->applyDiscount(100.0);
        $this->assertSame(100.0, $result['original']);
        $this->assertSame(95.0, $result['final']);
        $this->assertSame(5.0, $result['discount']);
        $this->assertTrue($result['applied']);
    }

    public function testNoDiscountWhenCampaignInactive(): void
    {
        $service = new CampaignService(campaignActive: false, discountRate: 0.05);

        $this->assertFalse($service->isActive());
        $this->assertSame(0.0, $service->getDiscountRate());
        $this->assertSame(0.0, $service->getDiscountPercentage());

        $result = $service->applyDiscount(100.0);
        $this->assertSame(100.0, $result['original']);
        $this->assertSame(100.0, $result['final']);
        $this->assertSame(0.0, $result['discount']);
        $this->assertFalse($result['applied']);
    }

    public function testDiscountRounding(): void
    {
        $service = new CampaignService(campaignActive: true, discountRate: 0.05);

        // 217 * 0.05 = 10.85
        $result = $service->applyDiscount(217.0);
        $this->assertSame(10.85, $result['discount']);
        $this->assertSame(206.15, $result['final']);
    }
}
