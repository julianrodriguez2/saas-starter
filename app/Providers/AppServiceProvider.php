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
            $limit = max((int) config('platform.rate_limits.organization_api_per_minute', 60), 1);

            return Limit::perMinute($limit)->by($limiterKey)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Rate limit exceeded.',
                ], 429);
            });
        });

        RateLimiter::for('org-member-invite', function (Request $request): Limit {
            $limit = max((int) config('platform.rate_limits.member_invites_per_minute', 20), 1);

            return Limit::perMinute($limit)
                ->by($this->mutationRateLimitKey($request))
                ->response(fn () => $this->tooManyAttemptsResponse(
                    $request,
                    'Too many member invites. Please wait before trying again.'
                ));
        });

        RateLimiter::for('org-api-key-create', function (Request $request): Limit {
            $limit = max((int) config('platform.rate_limits.api_key_creations_per_minute', 10), 1);

            return Limit::perMinute($limit)
                ->by($this->mutationRateLimitKey($request))
                ->response(fn () => $this->tooManyAttemptsResponse(
                    $request,
                    'Too many API key creation attempts. Please wait before trying again.'
                ));
        });

        RateLimiter::for('org-billing-checkout', function (Request $request): Limit {
            $limit = max((int) config('platform.rate_limits.billing_checkout_per_minute', 5), 1);

            return Limit::perMinute($limit)
                ->by($this->mutationRateLimitKey($request))
                ->response(fn () => $this->tooManyAttemptsResponse(
                    $request,
                    'Too many checkout attempts. Please wait before trying again.'
                ));
        });
    }

    private function mutationRateLimitKey(Request $request): string
    {
        $organizationId = $this->resolveOrganizationIdForRateLimit($request);
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

        return "org:{$organizationId}:user:{$userId}";
    }

    private function resolveOrganizationIdForRateLimit(Request $request): string
    {
        $attributeOrganization = $request->attributes->get('currentOrganization');

        if ($attributeOrganization instanceof Organization && is_string($attributeOrganization->id)) {
            return $attributeOrganization->id;
        }

        $sessionOrganizationId = $request->hasSession()
            ? $request->session()->get('organization_id')
            : null;

        if (is_string($sessionOrganizationId) && $sessionOrganizationId !== '') {
            return $sessionOrganizationId;
        }

        return 'unknown';
    }

    private function tooManyAttemptsResponse(Request $request, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 429);
        }

        return redirect()->back()
            ->withErrors([
                'rate_limit' => $message,
            ])
            ->with('error', $message);
    }
}
