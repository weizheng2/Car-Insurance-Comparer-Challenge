<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\DTO\Request\QuoteRequest;
use App\DTO\Response\CalculateResponse;
use App\DTO\Response\Quote;
use App\Exception\ProviderException;
use App\Service\Provider\ProviderInterface;
use App\Service\Campaign\CampaignService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class QuoteComparisonService
{
    /**
     * @param iterable<ProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly CampaignService $campaignService,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly int $providerTimeout,
    ) {}

    public function compare(QuoteRequest $request): CalculateResponse
    {
        $this->logger->info('Compare quotes', [
            'driver_age' => $request->driverAge,
            'car_type' => $request->carType->value,
            'car_use' => $request->carUse->value,
        ]);

        $responses = [];
        $providerByResponse = new \SplObjectStorage();
        foreach ($this->providers as $provider) {
            $responses[] = $response = $provider->requestQuote($request);
            $providerByResponse[$response] = $provider;
        }

        $quotes = [];
        $failedProviders = [];
        $discountRate = $this->campaignService->getDiscountRate();

        foreach ($this->httpClient->stream($responses, (float) $this->providerTimeout) as $response => $chunk) {
            $provider = $providerByResponse[$response];

            if ($chunk->isTimeout()) {
                $failedProviders[] = $this->handleTimeout($response, $provider);
                continue;
            }

            if (!$chunk->isLast()) {
                continue;
            }

            $result = $this->processSuccessfulResponse($response, $provider, $discountRate);
            if ($result instanceof Quote) {
                $quotes[] = $result;
            } else {
                $failedProviders[] = $result;
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

    private function handleTimeout(ResponseInterface $response, ProviderInterface $provider): string
    {
        $response->cancel();
        $exception = ProviderException::timeout($provider->getName(), $this->providerTimeout);
        $this->logger->warning('Provider failed (timeout)', [
            'provider' => $exception->provider,
            'error' => $exception->getMessage(),
        ]);
        return $exception->provider;
    }

    /**
     * @return Quote|string Returns Quote on success, provider name on failure
     */
    private function processSuccessfulResponse(ResponseInterface $response, ProviderInterface $provider, float $discountRate): Quote|string {
        try {
            if ($response->getStatusCode() !== 200) {
                throw ProviderException::httpError(
                    $provider->getName(),
                    $response->getStatusCode(),
                    $response->getContent(false)
                );
            }

            $price = $provider->parseResponseContent($response->getContent());
            $discountAmount = round($price * $discountRate, 2);
            $finalPrice = round($price - $discountAmount, 2);

            $this->logger->info('Quote received', ['provider' => $provider->getName(), 'price' => $price]);

            return Quote::create(
                provider: $provider->getName(),
                originalPrice: $price,
                finalPrice: $finalPrice,
                discountAmount: $discountAmount,
                hasDiscount: $discountRate > 0,
            );
        } catch (ProviderException $e) {
            $this->logProviderFailure($e);
            return $e->provider;
        } catch (TimeoutExceptionInterface $e) {
            $exception = ProviderException::timeout($provider->getName(), $this->providerTimeout);
            $this->logProviderFailure($exception);
            return $exception->provider;
        } catch (\Throwable $e) {
            $exception = ProviderException::connectionError($provider->getName(), $e);
            $this->logProviderFailure($exception);
            return $exception->provider;
        }
    }

    private function logProviderFailure(ProviderException $e): void
    {
        $this->logger->warning('Provider failed', [
            'provider' => $e->provider,
            'error' => $e->getMessage(),
        ]);
    }
}
