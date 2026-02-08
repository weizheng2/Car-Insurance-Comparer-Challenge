<?php

declare(strict_types=1);

namespace App\Enum;

enum CarUse: string
{
    case PRIVATE = 'private';
    case COMMERCIAL = 'commercial';

    private const ALIASES = [
        'private' => self::PRIVATE,
        'privado' => self::PRIVATE,
        'commercial' => self::COMMERCIAL,
        'comercial' => self::COMMERCIAL,
    ];

    public function isCommercial(): bool
    {
        return $this === self::COMMERCIAL;
    }

    public static function fromValue(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (!isset(self::ALIASES[$normalized])) {
            throw new \InvalidArgumentException(sprintf('Invalid car use: "%s".', $value));
        }

        return self::ALIASES[$normalized];
    }
}
