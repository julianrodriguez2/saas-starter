<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\CurrentApiOrganization;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationCanWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolveOrganization($request);
        $organizationContextMessage = 'Organization context is required.';

        if ($organization === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $organizationContextMessage,
                ], 422);
            }

            return redirect()->route('organizations.create')
                ->withErrors([
                    'organization' => $organizationContextMessage,
                ])
                ->with('error', $organizationContextMessage);
        }

        if ($organization->canPerformWrites()) {
            return $next($request);
        }

        $message = 'Organization is suspended.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()->back()
            ->withErrors([
                'organization' => $message,
            ])
            ->with('error', $message);
    }

    private function resolveOrganization(Request $request): ?Organization
    {
        if (app()->bound(CurrentApiOrganization::class)) {
            return app(CurrentApiOrganization::class)->organization;
        }

        if (app()->bound(CurrentOrganization::class)) {
            return app(CurrentOrganization::class)->organization;
        }

        $attributeOrganization = $request->attributes->get('currentOrganization');

        return $attributeOrganization instanceof Organization
            ? $attributeOrganization
            : null;
    }
}
