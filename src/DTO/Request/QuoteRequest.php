<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Enum\CarType;
use App\Enum\CarUse;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class QuoteRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Driver age is required')]
        #[Assert\Range(
            min: 18,
            max: 120,
            notInRangeMessage: 'Driver age must be between {{ min }} and {{ max }} years'
        )]
        public int $driverAge,

        #[Assert\NotNull(message: 'Car type is required')]
        public CarType $carType,

        #[Assert\NotNull(message: 'Car use is required')]
        public CarUse $carUse,
    ) {}

    /**
     * Creates a QuoteRequest from raw input data.
     *
     * @throws \InvalidArgumentException if required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        // Calculate age from birthday
        $driverAge = $data['driver_age'] ?? self::calculateAgeFromBirthday($data['driver_birthday'] ?? null);

        if ($driverAge === null) {
            throw new \InvalidArgumentException('Either driver_age or driver_birthday is required');
        }

        $carType = isset($data['car_type']) || isset($data['car_form'])
            ? CarType::fromValue($data['car_type'] ?? $data['car_form'])
            : throw new \InvalidArgumentException('car_type is required');

        $carUse = isset($data['car_use'])
            ? CarUse::fromValue($data['car_use'])
            : throw new \InvalidArgumentException('car_use is required');

        return new self(
            driverAge: $driverAge,
            carType: $carType,
            carUse: $carUse,
        );
    }

    private static function calculateAgeFromBirthday(?string $birthday): ?int
    {
        if ($birthday === null || $birthday === '') {
            return null;
        }

        try {
            $birthDate = new \DateTimeImmutable($birthday);
            $today = new \DateTimeImmutable('today');
            $age = $today->diff($birthDate)->y;

            return $age;
        } catch (\Exception) {
            throw new \InvalidArgumentException(sprintf('Invalid birthday format: "%s". Expected format: YYYY-MM-DD', $birthday));
        }
    }

    public function toArray(): array
    {
        return [
            'driver_age' => $this->driverAge,
            'car_type' => $this->carType->value,
            'car_use' => $this->carUse->value,
        ];
    }
}
