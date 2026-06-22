<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\DataTransfer\PaymentRequest;
use App\Domain\Payments\DataTransfer\PaymentResult;
use App\Domain\Payments\DataTransfer\PaymentStatus;
use InvalidArgumentException;

/**
 * Records a mobile-money payment the cashier has already confirmed on their phone.
 *
 * Future replacement: SelcomGateway / PesapalGateway / MpesaC2BGateway that
 * actually initiate an STK push and listen for a callback.
 */
final class ManualMobileMoneyGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $providerCode,
        private readonly string $providerLabel,
    ) {}

    public function code(): string
    {
        return $this->providerCode;
    }

    public function label(): string
    {
        return $this->providerLabel;
    }

    public function requiresReference(): bool
    {
        return true;
    }

    public function charge(PaymentRequest $request): PaymentResult
    {
        if ($request->reference === null || $request->reference === '') {
            throw new InvalidArgumentException("{$this->providerLabel} requires a transaction reference.");
        }

        return new PaymentResult(
            status: PaymentStatus::Confirmed,
            reference: $request->reference,
            providerPayload: [
                'payer_phone' => $request->payerPhone,
                'manual_entry' => true,
            ],
        );
    }

    public function verify(string $reference): PaymentStatus
    {
        return PaymentStatus::Confirmed;
    }
}
