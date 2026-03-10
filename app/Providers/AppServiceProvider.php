<?php

namespace App\Providers;

use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        Gate::policy(Organization::class, OrganizationPolicy::class);

        RateLimiter::for('organization-api', function (Request $request): Limit {
            $organizationId = $request->attributes->get('apiOrganizationId');
            $limiterKey = is_string($organizationId) && $organizationId !== ''
                ? "org:{$organizationId}"
                : 'org:unknown';

            return Limit::perMinute(60)->by($limiterKey)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Rate limit exceeded.',
                ], 429);
            });
        });
    }
}
