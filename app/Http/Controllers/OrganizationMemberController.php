<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        $organization->load([
            'users:id,name,email',
            'owner:id,name,email',
            'invites:id,organization_id,email,role,token,created_at',
        ]);

        $members = $organization->users
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $organization->owner_id === $user->id
                    ? OrganizationUser::ROLE_OWNER
                    : ($user->pivot?->role ?? OrganizationUser::ROLE_MEMBER),
            ])
            ->keyBy('id');

        if ($organization->owner !== null && ! $members->has($organization->owner->id)) {
            $members->put($organization->owner->id, [
                'id' => $organization->owner->id,
                'name' => $organization->owner->name,
                'email' => $organization->owner->email,
                'role' => OrganizationUser::ROLE_OWNER,
            ]);
        }

        return Inertia::render('Organizations/Members', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'currentUserRole' => $request->user()->roleInOrganization($organization),
            'members' => $members
                ->sortBy('name')
                ->values()
                ->all(),
            'invites' => $organization->invites
                ->map(fn (Invite $invite): array => [
                    'id' => $invite->id,
                    'email' => $invite->email,
                    'role' => $invite->role,
                    'created_at' => $invite->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function invite(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in([OrganizationUser::ROLE_ADMIN, OrganizationUser::ROLE_MEMBER])],
        ]);

        $actor = $request->user();
        $email = Str::lower($validated['email']);
        $role = $validated['role'];

        if ($actor->isAdmin($organization) && ! $actor->isOwner($organization) && $role === OrganizationUser::ROLE_ADMIN) {
            abort(403);
        }

        $existingUser = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($existingUser !== null) {
            if ($organization->owner_id === $existingUser->id) {
                return redirect()->back()->withErrors([
                    'email' => 'The organization owner is already a member.',
                ]);
            }

            if ($actor->isAdmin($organization) && ! $actor->isOwner($organization)) {
                if ($existingUser->isAdmin($organization) || $existingUser->isOwner($organization)) {
                    abort(403);
                }
            }

            $organization->users()->syncWithoutDetaching([
                $existingUser->id => ['role' => $role],
            ]);

            $organization->users()->updateExistingPivot($existingUser->id, ['role' => $role]);

            $organization->invites()
                ->whereRaw('lower(email) = ?', [$email])
                ->delete();

            return redirect()->back()->with('status', 'Member added to organization.');
        }

        $organization->invites()->updateOrCreate(
            ['email' => $email],
            [
                'role' => $role,
                'token' => (string) Str::uuid(),
                'created_at' => now(),
            ]
        );

        return redirect()->back()->with('status', 'Invitation created.');
    }

    public function destroy(Request $request, CurrentOrganization $currentOrganization, User $user): RedirectResponse
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        if (! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        if ($user->isOwner($organization)) {
            abort(403);
        }

        $actor = $request->user();

        if ($actor->isOwner($organization)) {
            $organization->users()->detach($user->id);

            return redirect()->back()->with('status', 'Member removed.');
        }

        if ($actor->isAdmin($organization)) {
            if ($user->isAdmin($organization)) {
                abort(403);
            }

            $organization->users()->detach($user->id);

            return redirect()->back()->with('status', 'Member removed.');
        }

        abort(403);
    }

    public function updateRole(Request $request, CurrentOrganization $currentOrganization, User $user): RedirectResponse
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        $validated = $request->validate([
            'role' => ['required', Rule::in([OrganizationUser::ROLE_ADMIN, OrganizationUser::ROLE_MEMBER])],
        ]);

        if (! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        if ($user->isOwner($organization)) {
            abort(403);
        }

        $actor = $request->user();
        $newRole = $validated['role'];

        if ($actor->isOwner($organization)) {
            $organization->users()->updateExistingPivot($user->id, ['role' => $newRole]);

            return redirect()->back()->with('status', 'Member role updated.');
        }

        if ($actor->isAdmin($organization)) {
            if ($newRole !== OrganizationUser::ROLE_MEMBER || $user->isAdmin($organization)) {
                abort(403);
            }

            $organization->users()->updateExistingPivot($user->id, ['role' => OrganizationUser::ROLE_MEMBER]);

            return redirect()->back()->with('status', 'Member role updated.');
        }

        abort(403);
    }

    private function resolveOrganization(CurrentOrganization $currentOrganization): Organization
    {
        $organization = $currentOrganization->organization;

        abort_if($organization === null, 404);

        return $organization;
    }
}
