<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\AdminImpersonation;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationFromSession
{
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

        if ($isSuperAdminImpersonating) {
            $organization = Organization::query()
                ->whereKey($organizationId)
                ->first();
        } else {
            $userId = $user->getKey();

            $organization = Organization::query()
                ->whereKey($organizationId)
                ->where(function ($query) use ($userId) {
                    $query->where('owner_id', $userId)
                        ->orWhereHas('users', function ($userQuery) use ($userId) {
                            $userQuery->where('users.id', $userId);
                        });
                })
                ->first();
        }

        if ($organization === null) {
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

        $currentOrganization = new CurrentOrganization($organization);

        app()->instance(CurrentOrganization::class, $currentOrganization);
        app()->instance('CurrentOrganization', $currentOrganization);

        $request->attributes->set('currentOrganization', $organization);

        return $next($request);
    }
}
