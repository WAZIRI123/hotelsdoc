<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Fiscal\Contracts\FiscalReceiptIssuer;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\PaymentGatewayManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, function (Container $app): PaymentGatewayManager {
            /** @var array<string, array{class: class-string<PaymentGateway>, options?: array<string, mixed>}> $gateways */
            $gateways = config('integrations.payments.gateways', []);

            return new PaymentGatewayManager($app, $gateways);
        });

        $this->app->singleton(FiscalReceiptIssuer::class, function (Container $app): FiscalReceiptIssuer {
            /** @var array<string, class-string<FiscalReceiptIssuer>> $issuers */
            $issuers = config('integrations.fiscal.issuers', []);
            $default = (string) config('integrations.fiscal.default', 'internal');

            if (! isset($issuers[$default])) {
                throw new InvalidArgumentException("No fiscal issuer registered for [{$default}].");
            }

            return $app->make($issuers[$default]);
        });
    }
}
