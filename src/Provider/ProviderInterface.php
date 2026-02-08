<?php
namespace App\Provider;

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
