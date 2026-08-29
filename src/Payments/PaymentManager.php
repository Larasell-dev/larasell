<?php

namespace Larasell\Larasell\Payments;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\PaymentProvider;

class PaymentManager
{
    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    public function default(): PaymentMethod
    {
        $handle = $this->config->get('larasell.payments.default', 'cash');

        if (! is_string($handle) || $handle === '') {
            throw new InvalidArgumentException('The default payment method must be a non-empty string.');
        }

        return $this->method($handle);
    }

    public function method(string $handle): PaymentMethod
    {
        $method = $this->config->get("larasell.payments.methods.{$handle}");

        if (! is_array($method)) {
            throw new InvalidArgumentException("Payment method [{$handle}] is not configured.");
        }

        $driver = $method['driver'] ?? null;
        $provider = $method['provider'] ?? null;
        $inventoryReservationMinutes = $method['inventory_reservation_minutes'] ?? null;

        if (! is_string($driver) || $driver === '' || ! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException("Payment method [{$handle}] must define a driver and provider.");
        }

        if (! is_a($provider, PaymentProvider::class, true)) {
            throw new InvalidArgumentException("Payment provider [{$provider}] must implement ".PaymentProvider::class.'.');
        }

        if ($inventoryReservationMinutes !== null
            && (! is_int($inventoryReservationMinutes) || $inventoryReservationMinutes < 1)) {
            throw new InvalidArgumentException(
                "Payment method [{$handle}] inventory_reservation_minutes must be a positive integer or null."
            );
        }

        return new PaymentMethod($handle, $driver, $provider, $inventoryReservationMinutes);
    }

    public function provider(PaymentMethod $method): PaymentProvider
    {
        return $this->container->make($method->provider);
    }
}
