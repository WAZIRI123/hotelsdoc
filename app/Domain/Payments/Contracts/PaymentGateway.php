<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DataTransfer\PaymentRequest;
use App\Domain\Payments\DataTransfer\PaymentResult;
use App\Domain\Payments\DataTransfer\PaymentStatus;

/**
 * Adapter contract for any payment method (cash, M-Pesa, Selcom, Pesapal, card, bank).
 *
 * MVP ships with manual adapters (Cash, ManualMobileMoney, ManualBank). Future
 * adapters (e.g. SelcomGateway, PesapalGateway) implement the same interface and
 * are registered in config/integrations.php — no changes to the calling code.
 */
interface PaymentGateway
{
    /**
     * Stable identifier persisted on the payments table (e.g. 'cash', 'mpesa', 'selcom').
     */
    public function code(): string;

    /**
     * Human-readable label for the UI.
     */
    public function label(): string;

    /**
     * Whether this gateway requires an external reference (e.g. M-Pesa transaction ID).
     */
    public function requiresReference(): bool;

    /**
     * Initiate or record a charge.
     *
     * For manual gateways (cash) this just stamps the payment as confirmed.
     * For real gateways (Selcom) this kicks off an STK push / hosted checkout.
     */
    public function charge(PaymentRequest $request): PaymentResult;

    /**
     * Look up the current status of a previously initiated payment.
     */
    public function verify(string $reference): PaymentStatus;
}
