<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DataTransfer\PaymentRequest;
use App\Domain\Payments\DataTransfer\PaymentResult;
use App\Domain\Payments\DataTransfer\PaymentStatus;
use InvalidArgumentException;

final class ManualBankGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $bankCode,
        private readonly string $bankLabel,
    ) {}

    public function code(): string
    {
        return $this->bankCode;
    }

    public function label(): string
    {
        return $this->bankLabel;
    }

    public function requiresReference(): bool
    {
        return true;
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        if ($request->reference === null || $request->reference === '') {
            throw new InvalidArgumentException("{$this->bankLabel} requires a deposit slip / control number.");
        }

        return new PaymentResult(
            status: PaymentStatus::Confirmed,
            reference: $request->reference,
            providerPayload: ['manual_entry' => true],
        );
    }

    public function verify(string $reference): PaymentStatus
    {
        return PaymentStatus::Confirmed;
    }
}
