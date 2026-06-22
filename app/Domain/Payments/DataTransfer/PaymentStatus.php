<?php

declare(strict_types=1);

namespace App\Domain\Payments\DataTransfer;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
