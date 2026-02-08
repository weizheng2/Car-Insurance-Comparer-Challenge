<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\DTO\Request\QuoteRequest;
use App\Exception\ProviderException;

interface ProviderInterface
{
    public function getName(): string;

    /**
     * Solicita un presupuesto al proveedor.
     *
     * @throws ProviderException Si el proveedor no responde correctamente
     */
    public function getQuote(QuoteRequest $request): float;
}
