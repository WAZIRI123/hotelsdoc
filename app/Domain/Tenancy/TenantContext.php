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
 */
final class TenantContext
{
    private ?Hotel $hotel = null;

    public function set(Hotel $hotel): void
    {
        $this->hotel = $hotel;
    }

    public function clear(): void
    {
        $this->hotel = null;
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
}
