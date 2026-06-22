<?php

declare(strict_types=1);

use App\Domain\Fiscal\Issuers\InternalReceiptIssuer;
use App\Domain\Payments\Gateways\CashGateway;
use App\Domain\Payments\Gateways\ManualBankGateway;
use App\Domain\Payments\Gateways\ManualMobileMoneyGateway;

/*
 * Registration point for adapters that implement the port interfaces in
 * app/Domain/*. Adding a new gateway/issuer/channel is just an entry here
 * plus the adapter class — no changes to call sites.
 */
return [
    'payments' => [
        'default' => env('PAYMENT_DEFAULT', 'cash'),

        'gateways' => [
            'cash' => [
                'class' => CashGateway::class,
            ],
            'mpesa' => [
                'class' => ManualMobileMoneyGateway::class,
                'options' => [
                    'providerCode' => 'mpesa',
                    'providerLabel' => 'M-Pesa',
                ],
            ],
            'tigopesa' => [
                'class' => ManualMobileMoneyGateway::class,
                'options' => [
                    'providerCode' => 'tigopesa',
                    'providerLabel' => 'Tigo Pesa',
                ],
            ],
            'airtelmoney' => [
                'class' => ManualMobileMoneyGateway::class,
                'options' => [
                    'providerCode' => 'airtelmoney',
                    'providerLabel' => 'Airtel Money',
                ],
            ],
            'halopesa' => [
                'class' => ManualMobileMoneyGateway::class,
                'options' => [
                    'providerCode' => 'halopesa',
                    'providerLabel' => 'HaloPesa',
                ],
            ],
            'crdb' => [
                'class' => ManualBankGateway::class,
                'options' => [
                    'bankCode' => 'crdb',
                    'bankLabel' => 'CRDB Bank',
                ],
            ],
            'nmb' => [
                'class' => ManualBankGateway::class,
                'options' => [
                    'bankCode' => 'nmb',
                    'bankLabel' => 'NMB Bank',
                ],
            ],
        ],
    ],

    'fiscal' => [
        'default' => env('FISCAL_DEFAULT', 'internal'),
        'issuers' => [
            'internal' => InternalReceiptIssuer::class,
            // 'tra_vfd' => TraVfdIssuer::class, // future
        ],
    ],

    'channels' => [
        // 'booking_com' => BookingDotComAdapter::class, // future
        // 'expedia'     => ExpediaAdapter::class,        // future
    ],
];
