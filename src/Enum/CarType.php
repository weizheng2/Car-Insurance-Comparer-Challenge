<?php

namespace App\Enum;

enum CarType: string
{
    case TURISMO = 'turismo';
    case SUV = 'suv';
    case COMPACTO = 'compacto';

    private const ALIASES = [
        'turismo' => self::TURISMO,
        'sedan'   => self::TURISMO,
        'suv'     => self::SUV,
        'compacto'=> self::COMPACTO,
        'compact' => self::COMPACTO,
    ];

    public static function fromValue(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (!isset(self::ALIASES[$normalized])) {
            throw new \InvalidArgumentException(sprintf('Invalid car type: "%s".', $value));
        }

        return self::ALIASES[$normalized];
    }
}
