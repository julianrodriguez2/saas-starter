<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\PlatformCacheService;
use App\Support\AdminImpersonation;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationFromSession
{
    public function __construct(
        private readonly PlatformCacheService $platformCacheService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $organizationId = $request->session()->get('organization_id');
        $isSuperAdminImpersonating = AdminImpersonation::isActiveFor($request, $user);
        $impersonatedOrganizationId = AdminImpersonation::impersonatedOrganizationId($request);

        if ($isSuperAdminImpersonating && $impersonatedOrganizationId !== null && $organizationId !== $impersonatedOrganizationId) {
            $organizationId = $impersonatedOrganizationId;
            $request->session()->put('organization_id', $organizationId);
        }

        if (! is_string($organizationId) || $organizationId === '') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Organization context is required.',
                ], 422);
            }

            return redirect()->route('organizations.create');
        }

        $organizationSummary = null;

        if ($isSuperAdminImpersonating) {
            $organizationSummary = $this->resolveOrganizationSummary($organizationId);
        } else {
            $userId = (int) $user->getKey();
            $canAccessOrganization = $this->platformCacheService->rememberOrganizationAccess(
                userId: $userId,
                organizationId: $organizationId,
                resolver: function () use ($organizationId, $userId): bool {
                    $isOwner = Organization::query()
                        ->whereKey($organizationId)
                        ->where('owner_id', $userId)
                        ->exists();

                    if ($isOwner) {
                        return true;
                    }

                    return DB::table('organization_user')
                        ->where('organization_id', $organizationId)
                        ->where('user_id', $userId)
                        ->exists();
                }
            );

            if ($canAccessOrganization) {
                $organizationSummary = $this->resolveOrganizationSummary($organizationId);

                if ($organizationSummary === null) {
                    $this->platformCacheService->forgetOrganizationAccess($userId, $organizationId);
                }
            }
        }

        if (! is_array($organizationSummary)) {
            $request->session()->forget('organization_id');
            AdminImpersonation::clear($request);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not belong to the selected organization.',
                ], 403);
            }

            if ($isSuperAdminImpersonating) {
                return redirect()->route('admin.organizations.index')
                    ->with('error', 'Impersonation target is no longer available.');
            }

            abort(403);
        }

        $organization = $this->platformCacheService->hydrateOrganizationFromSummary($organizationSummary);
        $currentOrganization = new CurrentOrganization($organization);

        app()->instance(CurrentOrganization::class, $currentOrganization);
        app()->instance('CurrentOrganization', $currentOrganization);

        $request->attributes->set('currentOrganization', $organization);

        return $next($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveOrganizationSummary(string $organizationId): ?array
    {
        return $this->platformCacheService->rememberOrganizationSummary(
            $organizationId,
            function () use ($organizationId): ?array {
                $organization = Organization::query()
                    ->whereKey($organizationId)
                    ->first([
                        'id',
                        'name',
                        'owner_id',
                        'plan_id',
                        'trial_ends_at',
                        'is_suspended',
                        'suspended_at',
                        'suspension_reason',
                        'stripe_id',
                        'stripe_customer_id',
                        'stripe_subscription_id',
                        'created_at',
                        'updated_at',
                    ]);

                if ($organization === null) {
                    return null;
                }

                return $organization->getAttributes();
            }
        );
    }
}
