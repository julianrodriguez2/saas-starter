<?php

namespace App\Http\Middleware;

use App\Models\Organization;
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
        $currentOrganization = app()->bound(CurrentOrganization::class)
            ? app(CurrentOrganization::class)
            : new CurrentOrganization();

        if ($request->user() === null) {
            $organizations = [];
        } else {
            $memberOrganizations = $request->user()
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

            $ownedOrganizations = $request->user()
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'organization' => [
                'current' => $currentOrganization->toArray(),
                'all' => $organizations,
            ],
        ];
    }
}
