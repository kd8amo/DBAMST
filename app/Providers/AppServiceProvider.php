<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Device;
use App\Models\FaultReport;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\TestSystem;
use App\Models\User;

use App\Policies\ApiKeyPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\DevicePolicy;
use App\Policies\FaultReportPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\TestSystemPolicy;
use App\Policies\UserPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Model → Policy mappings.
        // Gates defined on non-model actions (e.g. bulkImport, logEvent) are
        // called via Gate::allows('logEvent') or $this->authorize('logEvent',
        // MaintenanceEvent::class) in controllers — Laravel resolves the
        // policy from the model class passed as the second argument.
        Gate::policy(Device::class,          DevicePolicy::class);
        Gate::policy(TestSystem::class,      TestSystemPolicy::class);
        Gate::policy(Booking::class,         BookingPolicy::class);
        Gate::policy(FaultReport::class,     FaultReportPolicy::class);
        Gate::policy(MaintenanceEvent::class, MaintenancePolicy::class);
        Gate::policy(MaintenanceSchedule::class, MaintenancePolicy::class);
        Gate::policy(User::class,            UserPolicy::class);
        Gate::policy(ApiKey::class,          ApiKeyPolicy::class);
        Gate::policy(AuditLog::class,        AuditLogPolicy::class);
    }
}
