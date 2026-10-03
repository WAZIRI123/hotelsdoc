<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\Hotel;
use RuntimeException;

/**
 * Holds the active tenant (Hotel) for the current request/job.
 *
 * Set in a middleware after auth — every BelongsToTenant model reads from here
 * for its global scope and auto-fill of tenant_id.
 *
 * The hotel's timezone becomes the application timezone while it is active,
 * so "today", business dates and stored timestamps follow the hotel's clock.
 */
final class TenantContext
{
    private ?Hotel $hotel = null;

    private ?string $defaultTimezone = null;

    public function set(Hotel $hotel): void
    {
        $this->defaultTimezone ??= (string) config('app.timezone', 'UTC');
        $this->hotel = $hotel;
        $this->useTimezone($hotel->timezone ?: $this->defaultTimezone);
    }

    public function clear(): void
    {
        $this->hotel = null;

        if ($this->defaultTimezone !== null) {
            $this->useTimezone($this->defaultTimezone);
        }
    }

    public function has(): bool
    {
        return $this->hotel !== null;
    }

    public function current(): Hotel
    {
        if ($this->hotel === null) {
            throw new RuntimeException('No active tenant. Did the tenancy middleware run?');
        }

        return $this->hotel;
    }

    public function id(): int
    {
        return $this->current()->getKey();
    }

    private function useTimezone(string $timezone): void
    {
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }
}
