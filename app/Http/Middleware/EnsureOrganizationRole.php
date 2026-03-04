<?php

namespace App\Http\Middleware;

use App\Models\OrganizationUser;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $currentOrganization = app()->bound(CurrentOrganization::class)
            ? app(CurrentOrganization::class)
            : null;
        $organization = $currentOrganization?->organization;

        if ($user === null || $organization === null) {
            abort(403);
        }

        if ($user->isOwner($organization)) {
            return $next($request);
        }

        $requiredRole = strtolower($role);

        $authorized = match ($requiredRole) {
            OrganizationUser::ROLE_ADMIN => $user->isAdmin($organization),
            OrganizationUser::ROLE_MEMBER => $user->belongsToOrganization($organization),
            OrganizationUser::ROLE_OWNER => $user->isOwner($organization),
            default => false,
        };

        abort_if(! $authorized, 403);

        return $next($request);
    }
}
