<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __invoke(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->organization?->load([
            'owner:id,name,email',
            'users:id,name,email',
        ]);

        abort_if($organization === null, 404);

        Gate::authorize('view', $organization);

        $members = $organization->users
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $organization->owner_id === $user->id
                    ? 'owner'
                    : ($user->pivot?->role ?? 'member'),
            ])
            ->keyBy('id');

        if ($organization->owner !== null && ! $members->has($organization->owner->id)) {
            $members->put($organization->owner->id, [
                'id' => $organization->owner->id,
                'name' => $organization->owner->name,
                'email' => $organization->owner->email,
                'role' => 'owner',
            ]);
        }

        return Inertia::render('Organizations/Settings', [
            'settingsOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'owner' => [
                'id' => $organization->owner?->id,
                'name' => $organization->owner?->name ?? 'Unknown',
            ],
            'members' => $members->values()->all(),
        ]);
    }
}
