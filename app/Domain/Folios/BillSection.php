<?php

declare(strict_types=1);

namespace App\Domain\Folios;

/**
 * The parts of a guest billing that can be paid separately: the stay itself,
 * what was bought at the outlets, and extra services.
 */
enum BillSection: string
{
    case Accommodation = 'accommodation';
    case Outlets = 'outlets';
    case Extras = 'extras';

    /**
     * Room nights, room adjustments, discounts and any other charge belong to
     * accommodation; POS orders to outlets; extra orders to extras.
     */
    public static function forSourceType(string $sourceType): self
    {
        return match ($sourceType) {
            'pos_order' => self::Outlets,
            'extra_order' => self::Extras,
            default => self::Accommodation,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Accommodation => __('Accommodation'),
            self::Outlets => __('Outlets'),
            self::Extras => __('Extra services'),
        };
    }
}
