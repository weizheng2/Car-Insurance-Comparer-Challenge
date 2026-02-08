<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Request\QuoteRequest;
use App\Enum\CarType;
use App\Enum\CarUse;
use App\Service\Campaign\CampaignService;
use App\Service\Quote\QuoteComparisonService;
use App\Service\Provider\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests for comparison and sorting logic.
 * Uses mocked providers to avoid HTTP calls.
 */
final class QuoteComparisonServiceTest extends TestCase
{
    public function testQuotesSortedByPriceAscending(): void
    {
        $providers = [
            $this->createMockProvider('provider-b', 310.0),
            $this->createMockProvider('provider-a', 227.0),
            $this->createMockProvider('provider-c', 250.0),
        ];

        $service = $this->createService($providers, campaignActive: false);
        $request = new QuoteRequest(30, CarType::TURISMO, CarUse::PRIVATE);

        $response = $service->compare($request);

        $quotes = $response->quotes;
        $this->assertCount(3, $quotes);
        $this->assertSame(227.0, $quotes[0]->finalPrice);
        $this->assertSame(250.0, $quotes[1]->finalPrice);
        $this->assertSame(310.0, $quotes[2]->finalPrice);
    }

    public function testCampaignDiscountApplied(): void
    {
        $providers = [$this->createMockProvider('provider-a', 200.0)];

        $service = $this->createService($providers, campaignActive: true);
        $request = new QuoteRequest(30, CarType::TURISMO, CarUse::PRIVATE);

        $response = $service->compare($request);

        $this->assertTrue($response->campaignActive);
        $this->assertSame(5.0, $response->discountPercentage);

        $quote = $response->quotes[0];
        $this->assertSame(200.0, $quote->originalPrice);
        $this->assertSame(190.0, $quote->finalPrice);
        $this->assertSame(10.0, $quote->discountAmount);
        $this->assertTrue($quote->hasDiscount);
    }

    public function testCampaignInactiveNoDiscount(): void
    {
        $providers = [$this->createMockProvider('provider-a', 200.0)];

        $service = $this->createService($providers, campaignActive: false);
        $request = new QuoteRequest(30, CarType::TURISMO, CarUse::PRIVATE);

        $response = $service->compare($request);

        $this->assertFalse($response->campaignActive);
        $this->assertSame(0.0, $response->discountPercentage);

        $quote = $response->quotes[0];
        $this->assertSame(200.0, $quote->originalPrice);
        $this->assertSame(200.0, $quote->finalPrice);
        $this->assertSame(0.0, $quote->discountAmount);
        $this->assertFalse($quote->hasDiscount);
    }

    private function createMockProvider(string $name, float $price): ProviderInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn((string) $price);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getName')->willReturn($name);
        $provider->method('requestQuote')->willReturn($response);
        $provider->method('parseResponseContent')->willReturn($price);

        return $provider;
    }

    private function createService(array $providers, bool $campaignActive): QuoteComparisonService
    {
        $request = new QuoteRequest(30, CarType::TURISMO, CarUse::PRIVATE);

        $generator = (function () use ($providers, $request): \Generator {
            foreach ($providers as $provider) {
                $response = $provider->requestQuote($request);
                yield $response => new LastChunk();
            }
        })();

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('stream')->willReturn(new ResponseStream($generator));

        return new QuoteComparisonService(
            providers: $providers,
            campaignService: new CampaignService($campaignActive, 0.05),
            logger: new NullLogger(),
            httpClient: $httpClient,
            providerTimeout: 10,
        );
    }
}
