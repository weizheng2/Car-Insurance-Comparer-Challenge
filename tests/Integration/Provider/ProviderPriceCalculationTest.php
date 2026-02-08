<?php

declare(strict_types=1);

namespace App\Tests\Integration\Provider;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests provider price calculations against specification.
 * In test env, providers skip sleep and random errors for deterministic results.
 */
final class ProviderPriceCalculationTest extends WebTestCase
{
    /**
     * Provider A: Base 217€, Age 25-55 +0€, Compact +10€, Private = 227€
     */
    public function testProviderACalculation(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/provider-a/quote', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'driver_age' => 30,
            'car_form' => 'compact',
            'car_use' => 'private',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('price', $data);

        $price = (float) preg_replace('/[^0-9.]/', '', $data['price']);
        $this->assertSame(227.0, $price);
    }

    /**
     * Provider A: Age 18-24 +70€, SUV +100€ = 217+70+100 = 387€
     */
    public function testProviderAAgeAndSuv(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/provider-a/quote', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'driver_age' => 20,
            'car_form' => 'suv',
            'car_use' => 'private',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $price = (float) preg_replace('/[^0-9.]/', '', $data['price']);
        $this->assertSame(387.0, $price);
    }

    /**
     * Provider B: Base 250€, Age 30-59 +20€, Turismo +30€ = 300€
     */
    public function testProviderBCalculation(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/provider-b/quote', [], [], [
            'CONTENT_TYPE' => 'application/xml',
        ], '<SolicitudCotizacion>
            <EdadConductor>30</EdadConductor>
            <TipoCoche>turismo</TipoCoche>
            <UsoCoche>privado</UsoCoche>
            <ConductorOcasional>NO</ConductorOcasional>
        </SolicitudCotizacion>');

        $this->assertResponseIsSuccessful();
        $xml = simplexml_load_string($client->getResponse()->getContent());
        $this->assertNotFalse($xml);
        $price = (float) (string) $xml->Precio;
        $this->assertSame(300.0, $price);
    }

    /**
     * Provider B: Age 18-29 +50€, SUV +200€ = 250+50+200 = 500€
     */
    public function testProviderBAgeAndSuv(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/provider-b/quote', [], [], [
            'CONTENT_TYPE' => 'application/xml',
        ], '<SolicitudCotizacion>
            <EdadConductor>25</EdadConductor>
            <TipoCoche>suv</TipoCoche>
            <UsoCoche>privado</UsoCoche>
            <ConductorOcasional>NO</ConductorOcasional>
        </SolicitudCotizacion>');

        $this->assertResponseIsSuccessful();
        $xml = simplexml_load_string($client->getResponse()->getContent());
        $price = (float) (string) $xml->Precio;
        $this->assertSame(500.0, $price);
    }

    /**
     * Provider C: Base 195€, Age 26-45 +10€, Turismo +25€ = 230€
     */
    public function testProviderCCalculation(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/provider-c/quote', [], [], [
            'CONTENT_TYPE' => 'text/csv',
        ], "driver_age,car_type,car_use\n30,T,P");

        $this->assertResponseIsSuccessful();
        $lines = explode("\n", trim($client->getResponse()->getContent()));
        $headers = str_getcsv($lines[0]);
        $values = str_getcsv($lines[1]);
        $data = array_combine($headers, $values);
        $price = (float) $data['price'];
        $this->assertSame(230.0, $price);
    }
}
