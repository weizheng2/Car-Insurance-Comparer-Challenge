<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\QuoteRequest;
use App\Service\Quote\QuoteComparisonService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class CalculateController extends AbstractController
{
    public function __construct(
        private readonly QuoteComparisonService $comparisonService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/calculate', name: 'api_calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $this->logger->info('Init calculate request');

        try {
            $data = $this->parseRequest($request);
            $quoteRequest = QuoteRequest::fromArray($data);
            $response = $this->comparisonService->compare($quoteRequest);

            return new JsonResponse($response->toArray(), Response::HTTP_OK);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Validation failed', ['error' => $e->getMessage()]);
            return new JsonResponse([
                'error' => 'Validation failed',
                'details' => [$e->getMessage()],
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function parseRequest(Request $request): array
    {
        $content = $request->getContent();
        if (empty($content)) {
            throw new \InvalidArgumentException('Request body is required');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON in request body');
        }

        return $data;
    }
}
