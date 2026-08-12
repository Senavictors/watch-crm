<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductReturnPolicy;
use App\Policies\UserPolicy;
use App\Support\CrmPermissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        // TASK-026 (CA-04): registro explícito em vez de depender da
        // descoberta automática — o model se chama `ProductReturn` mas a
        // tabela é `returns`, e registro explícito é o padrão já usado pelas
        // outras policies deste projeto.
        Gate::policy(ProductReturn::class, ProductReturnPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        foreach (CrmPermissions::ALL as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);

        RateLimiter::for('password-recovery', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
        ]);

        RateLimiter::for('ai-summary', fn (Request $request) => [
            Limit::perMinute(10)->by('ai-summary:'.($request->user()?->id ?? $request->ip())),
        ]);
    }
}
