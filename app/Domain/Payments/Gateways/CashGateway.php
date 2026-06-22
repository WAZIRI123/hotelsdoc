<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DataTransfer\PaymentRequest;
use App\Domain\Payments\DataTransfer\PaymentResult;
use App\Domain\Payments\DataTransfer\PaymentStatus;
use Illuminate\Support\Str;

final class CashGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'cash';
    }

    public function label(): string
    {
        return 'Cash';
    }

    public function requiresReference(): bool
    {
        return false;
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::Confirmed,
            reference: $request->reference ?? 'CASH-'.Str::upper(Str::random(8)),
            providerPayload: ['received_by' => 'front_desk'],
        );
    }

    public function verify(string $reference): PaymentStatus
    {
        return PaymentStatus::Confirmed;
    }
}
