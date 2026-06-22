<?php

declare(strict_types=1);

namespace App\Domain\Payments\DataTransfer;

final readonly class PaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        public string $reference,
        /** @var array<string, mixed> */
        public array $providerPayload = [],
        public ?string $message = null,
    ) {}
}
