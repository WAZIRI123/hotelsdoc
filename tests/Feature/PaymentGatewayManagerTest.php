<?php

declare(strict_types=1);

use App\Domain\Payments\DataTransfer\PaymentRequest;
use App\Domain\Payments\DataTransfer\PaymentStatus;
use App\Domain\Payments\Gateways\CashGateway;
use App\Domain\Payments\Gateways\ManualMobileMoneyGateway;
use App\Domain\Payments\PaymentGatewayManager;
use App\Support\Money;

it('resolves cash gateway and charges immediately', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $gateway = $manager->for('cash');

    expect($gateway)->toBeInstanceOf(CashGateway::class);

    $result = $gateway->charge(new PaymentRequest(
        folioUuid: 'folio-uuid-1',
        amount: Money::fromMajor(50_000),
    ));

    expect($result->status)->toBe(PaymentStatus::Confirmed)
        ->and($result->reference)->toStartWith('CASH-');
});

it('resolves mpesa gateway as a configured manual mobile money adapter', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $gateway = $manager->for('mpesa');

    expect($gateway)->toBeInstanceOf(ManualMobileMoneyGateway::class)
        ->and($gateway->code())->toBe('mpesa')
        ->and($gateway->label())->toBe('M-Pesa')
        ->and($gateway->requiresReference())->toBeTrue();
});

it('throws when a gateway is unknown', function (): void {
    app(PaymentGatewayManager::class)->for('does-not-exist');
})->throws(InvalidArgumentException::class);

it('rejects manual mobile money charges without a reference', function (): void {
    app(PaymentGatewayManager::class)->for('mpesa')->charge(new PaymentRequest(
        folioUuid: 'folio-uuid-1',
        amount: Money::fromMajor(50_000),
    ));
})->throws(InvalidArgumentException::class);
