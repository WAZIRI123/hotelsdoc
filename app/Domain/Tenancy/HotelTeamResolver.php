<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Resolves Spatie's "team_id" to the current hotel.
 *
 * Reads from the TenantContext by default so a logged-in user's role
 * lookups are scoped to their active hotel. Explicit set() overrides
 * the context (used by seeders, console commands, and tests that
 * operate outside a request).
 */
final class HotelTeamResolver implements PermissionsTeamResolver
{
    private int|string|null $override = null;

    private bool $hasOverride = false;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->override = $id;
        $this->hasOverride = true;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        if ($this->hasOverride) {
            return $this->override;
        }

        $context = app(TenantContext::class);

        return $context->has() ? $context->id() : null;
    }
}
