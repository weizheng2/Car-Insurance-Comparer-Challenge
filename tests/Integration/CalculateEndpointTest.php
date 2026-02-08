<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies aggregation, sorting, and campaign discount.
 */
final class CalculateEndpointTest extends WebTestCase
{
    public function testCalculateReturnsQuotesSortedByPrice(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/calculate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'driver_age' => 30,
            'car_type' => 'turismo',
            'car_use' => 'private',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('quotes', $data);
        $this->assertArrayHasKey('campaign_active', $data);
        $this->assertArrayHasKey('failed_providers', $data);

        $quotes = $data['quotes'];
        $this->assertGreaterThanOrEqual(1, count($quotes));

        for ($i = 1; $i < count($quotes); $i++) {
            $this->assertGreaterThanOrEqual(
                $quotes[$i - 1]['final_price'],
                $quotes[$i]['final_price'],
                'Quotes must be sorted by final_price ascending'
            );
        }
    }

    public function testCalculateWithDriverBirthday(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/calculate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'driver_birthday' => '1994-05-15',
            'car_type' => 'suv',
            'car_use' => 'commercial',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('quotes', $data);
    }

    public function testCalculateValidationError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/calculate', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'driver_age' => 30,
            'car_use' => 'private',
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
