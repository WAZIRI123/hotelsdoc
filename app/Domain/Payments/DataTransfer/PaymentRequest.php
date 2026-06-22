<?php

declare(strict_types=1);

namespace App\Domain\Payments\DataTransfer;

use App\Support\Money;

final readonly class PaymentRequest
{
    public function __construct(
        public string $folioUuid,
        public Money $amount,
        public ?string $reference = null,
        public ?string $payerPhone = null,
        public ?string $payerName = null,
        public ?string $note = null,
    ) {}
}
