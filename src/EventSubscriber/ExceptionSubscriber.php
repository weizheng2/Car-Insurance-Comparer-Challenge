<?php
namespace App\EventSubscriber;

use App\Exception\ProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Global exception handler for API errors.
 * 1. Consistent Error Response:
 *    All errors return JSON with consistent structure for frontend handling.
 *
 * 2. Logging:
 *    All exceptions are logged with appropriate severity level.
 *    Provider exceptions include provider context for debugging.
 *
 * 3. Security:
 *    In production, internal error details are hidden from response.
 *    Full details are only logged, not exposed.
 *
 * 4. Error Categories:
 *    - Validation errors (400): User input issues
 *    - Provider errors (502/503/504): External service issues
 *    - Server errors (500): Internal bugs (should be rare)
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests (JSON responses)
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Log the exception
        $this->logException($exception, $request->getPathInfo());

        // Create appropriate response
        $response = $this->createErrorResponse($exception);

        $event->setResponse($response);
    }

    /**
     * Log exception with appropriate context.
     */
    private function logException(\Throwable $exception, string $path): void
    {
        $context = [
            'path' => $path,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ];

        if ($exception instanceof ProviderException) {
            $context['provider'] = $exception->getProviderName();
            $this->logger->warning('Provider exception', $context);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $context['status_code'] = $exception->getStatusCode();
            $this->logger->notice('HTTP exception', $context);
        } else {
            $context['trace'] = $exception->getTraceAsString();
            $this->logger->error('Unhandled exception', $context);
        }
    }

    /**
     * Create JSON error response based on exception type.
     */
    private function createErrorResponse(\Throwable $exception): JsonResponse
    {
        // Determine status code
        $statusCode = $this->getStatusCode($exception);

        // Build error response
        $data = [
            'error' => $this->getErrorMessage($exception, $statusCode),
            'code' => $statusCode,
        ];

        // Add details in development mode
        if ($this->environment === 'dev') {
            $data['details'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($exception instanceof ProviderException) {
                $data['details']['provider'] = $exception->getProviderName();
            }
        }

        return new JsonResponse($data, $statusCode);
    }

    /**
     * Get HTTP status code for exception.
     */
    private function getStatusCode(\Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if ($exception instanceof ProviderException) {
            return $exception->getCode() ?: Response::HTTP_BAD_GATEWAY;
        }

        if ($exception instanceof \InvalidArgumentException) {
            return Response::HTTP_BAD_REQUEST;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Get user-friendly error message.
     */
    private function getErrorMessage(\Throwable $exception, int $statusCode): string
    {
        // For validation errors, show the actual message
        if ($exception instanceof \InvalidArgumentException) {
            return $exception->getMessage();
        }

        // For provider errors, show generic message
        if ($exception instanceof ProviderException) {
            return match ($statusCode) {
                Response::HTTP_GATEWAY_TIMEOUT => 'Provider request timed out',
                Response::HTTP_BAD_GATEWAY => 'Provider returned invalid response',
                Response::HTTP_SERVICE_UNAVAILABLE => 'Provider temporarily unavailable',
                default => 'Provider error occurred',
            };
        }

        // For HTTP exceptions, use the message
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getMessage() ?: Response::$statusTexts[$statusCode] ?? 'Error';
        }

        // For other errors, use generic message in production
        if ($this->environment === 'prod') {
            return 'An unexpected error occurred';
        }

        return $exception->getMessage();
    }
}
