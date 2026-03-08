<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\AdminImpersonation;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentOrganization = app()->bound(CurrentOrganization::class)
            ? app(CurrentOrganization::class)
            : new CurrentOrganization();
        $currentOrganizationPayload = $currentOrganization->toArray();

        if ($currentOrganizationPayload !== null && $user !== null) {
            $currentOrganizationPayload['role'] = $user->roleInOrganization(
                $currentOrganization->organization
            );
        }

        if ($user === null) {
            $organizations = [];
        } else {
            $memberOrganizations = $user
                ->organizations()
                ->select('organizations.id', 'organizations.name')
                ->get()
                ->mapWithKeys(fn (Organization $organization): array => [
                    $organization->id => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'role' => $organization->pivot?->role ?? 'member',
                    ],
                ]);

            $ownedOrganizations = $user
                ->ownedOrganizations()
                ->select('organizations.id', 'organizations.name')
                ->get()
                ->mapWithKeys(fn (Organization $organization): array => [
                    $organization->id => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'role' => 'owner',
                    ],
                ]);

            $organizations = $memberOrganizations
                ->union($ownedOrganizations)
                ->sortBy('name')
                ->values()
                ->all();
        }

        $isSuperAdmin = $user?->isSuperAdmin() ?? false;
        $isImpersonating = AdminImpersonation::isActiveFor($request, $user);
        $impersonatedOrganization = null;

        if ($isImpersonating) {
            $impersonatedOrganizationId = AdminImpersonation::impersonatedOrganizationId($request);

            if ($currentOrganizationPayload !== null && $currentOrganizationPayload['id'] === $impersonatedOrganizationId) {
                $impersonatedOrganization = [
                    'id' => $currentOrganizationPayload['id'],
                    'name' => $currentOrganizationPayload['name'],
                ];
            } elseif ($impersonatedOrganizationId !== null) {
                $impersonatedOrganization = Organization::query()
                    ->whereKey($impersonatedOrganizationId)
                    ->first(['id', 'name'])
                    ?->only(['id', 'name']);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'is_super_admin' => $isSuperAdmin,
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
            'organization' => [
                'current' => $currentOrganizationPayload,
                'all' => $organizations,
            ],
            'impersonation' => [
                'active' => $isImpersonating,
                'organization' => $impersonatedOrganization,
                'started_at' => $request->session()->get(AdminImpersonation::STARTED_AT),
            ],
        ];
    }
}
