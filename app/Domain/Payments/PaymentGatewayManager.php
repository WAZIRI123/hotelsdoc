<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves a PaymentGateway by its code (e.g. 'cash', 'mpesa', 'crdb').
 *
 * Adapters are registered in config/integrations.php and bound in
 * PaymentServiceProvider. Adding a new gateway never requires touching call sites.
 */
final class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
        /** @var array<string, array{class: class-string<PaymentGateway>, options?: array<string, mixed>}> */
        private readonly array $config,
    ) {}

    public function for(string $code): PaymentGateway
    {
        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        if (! isset($this->config[$code])) {
            throw new InvalidArgumentException("No payment gateway registered for code [{$code}].");
        }

        $entry = $this->config[$code];
        /** @var PaymentGateway $instance */
        $instance = $this->container->make($entry['class'], $entry['options'] ?? []);

        return $this->resolved[$code] = $instance;
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->config);
    }
}
