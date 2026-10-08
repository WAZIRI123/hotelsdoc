<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Fiscal\Actions\IssueReceiptForFolio;
use App\Domain\Folios\BillSection;
use App\Domain\Payments\DataTransfer\PaymentRequest;
use App\Domain\Payments\DataTransfer\PaymentStatus;
use App\Domain\Payments\PaymentGatewayManager;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a payment against a folio, going through the configured gateway.
 *
 * MVP gateways (Cash, ManualMobileMoney, ManualBank) settle synchronously and
 * return Confirmed immediately. When the real M-Pesa / Selcom adapters land,
 * pending payments will sit at status='pending' until a webhook flips them — the
 * folio bookkeeping below only credits paid_total on confirmed payments, so
 * pending payments are safe.
 *
 * A payment can be made against one section of the bill (accommodation,
 * outlets or extras) so the guest can pay those separately, or against the
 * whole bill when no section is given. See FolioSections.
 *
 * If the folio becomes fully paid, it is marked settled and a fiscal receipt
 * is issued via the FiscalReceiptIssuer port.
 */
final class RecordPayment
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly IssueReceiptForFolio $issueReceipt,
    ) {}

    public function execute(
        Folio $folio,
        string $methodCode,
        Money $amount,
        ?string $reference = null,
        ?string $payerPhone = null,
        ?string $payerName = null,
        ?User $receivedBy = null,
        ?string $note = null,
        ?BillSection $section = null,
    ): Payment {
        if ($folio->status === 'void') {
            throw new RuntimeException('Cannot record a payment against a voided guest billing.');
        }

        if ($amount->isZero() || $amount->isNegative()) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        $gateway = $this->gateways->for($methodCode);

        $result = $gateway->charge(new PaymentRequest(
            folioUuid: $folio->uuid,
            amount: $amount,
            reference: $reference,
            payerPhone: $payerPhone,
            payerName: $payerName,
            note: $note,
        ));

        return DB::transaction(function () use ($folio, $methodCode, $amount, $result, $receivedBy, $payerPhone, $note, $section): Payment {
            $payment = Payment::query()->create([
                'folio_id' => $folio->id,
                'bill_section' => $section?->value,
                'method_code' => $methodCode,
                'amount' => $amount,
                'currency' => $amount->currency,
                'reference' => $result->reference,
                'payer_phone' => $payerPhone,
                'note' => $note,
                'status' => $result->status->value,
                'provider_payload' => $result->providerPayload,
                'received_by' => $receivedBy?->id,
                'confirmed_at' => $result->status === PaymentStatus::Confirmed ? now() : null,
            ]);

            if ($result->status === PaymentStatus::Confirmed) {
                Folio::query()->whereKey($folio->id)->lockForUpdate()->first();
                $folio->refresh();
                $newPaid = $folio->paid_total->add($amount);
                $fullyPaid = $newPaid->amount >= $folio->total_amount->amount && $folio->total_amount->isPositive();

                $folio->update([
                    'paid_total' => $newPaid,
                    'status' => $fullyPaid ? 'settled' : $folio->status,
                    'settled_at' => $fullyPaid ? now() : $folio->settled_at,
                ]);

                if ($fullyPaid) {
                    $this->issueReceipt->execute($folio->fresh());
                }
            }

            return $payment;
        });
    }
}
