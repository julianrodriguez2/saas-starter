<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $organizationId = $request->session()->get('organization_id');

        if (! is_string($organizationId) || $organizationId === '') {
            return redirect()->route('organizations.create');
        }

        $userId = $request->user()->getKey();

        $organization = Organization::query()
            ->whereKey($organizationId)
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereHas('users', function ($userQuery) use ($userId) {
                        $userQuery->where('users.id', $userId);
                    });
            })
            ->first();

        if ($organization === null) {
            $request->session()->forget('organization_id');
            abort(403);
        }

        $currentOrganization = new CurrentOrganization($organization);

        app()->instance(CurrentOrganization::class, $currentOrganization);
        app()->instance('CurrentOrganization', $currentOrganization);

        $request->attributes->set('currentOrganization', $organization);

        return $next($request);
    }
}
