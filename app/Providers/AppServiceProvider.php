<?php

namespace App\Providers;

use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SetCurrentHotel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Livewire's /livewire/update endpoint does not re-run route-specific
        // middleware. Without this, the TenantContext singleton would be empty
        // on every component update and BelongsToTenant could not auto-fill
        // hotel_id, breaking any write triggered from a Livewire interaction.
        Livewire::addPersistentMiddleware([
            SetCurrentHotel::class,
            EnsureSuperAdmin::class,
        ]);

        // A super-admin who has entered a hotel via the switcher holds no role
        // there, so grant every hotel permission while a tenant is active.
        Gate::before(function (User $user): ?bool {
            return $user->isSuperAdmin() && app(TenantContext::class)->has() ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
