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
        $currentOrganization = new CurrentOrganization();

        if ($request->user() !== null) {
            $organizationId = $request->session()->get('organization_id');

            if (is_string($organizationId) && $organizationId !== '') {
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

                $currentOrganization = new CurrentOrganization($organization);
            }
        }

        app()->instance(CurrentOrganization::class, $currentOrganization);
        app()->instance('CurrentOrganization', $currentOrganization);

        $request->attributes->set('currentOrganization', $currentOrganization->organization);

        return $next($request);
    }
}
