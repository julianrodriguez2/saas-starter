<?php

namespace App\Http\Controllers;

use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Invite;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\EntitlementService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

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
            'membersOrganization' => [
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

    public function invite(
        Request $request,
        CurrentOrganization $currentOrganization,
        EntitlementService $entitlementService
    ): RedirectResponse
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        if ($organization->is_suspended) {
            return redirect()->back()
                ->withErrors(['organization' => 'Organization is suspended.'])
                ->with('error', 'Organization is suspended.');
        }

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

        try {
            $statusMessage = DB::transaction(function () use (
                $organization,
                $actor,
                $email,
                $role,
                $entitlementService
            ): string {
                $lockedOrganization = Organization::query()
                    ->whereKey($organization->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingUser = User::query()
                    ->whereRaw('lower(email) = ?', [$email])
                    ->first();

                $willAddMember = $existingUser === null
                    || ! $existingUser->belongsToOrganization($lockedOrganization);

                if ($willAddMember) {
                    $entitlementService->checkLimit(
                        $lockedOrganization,
                        'team_members',
                        $this->currentMemberCount($lockedOrganization) + 1
                    );
                }

                if ($existingUser !== null) {
                    if ($lockedOrganization->owner_id === $existingUser->id) {
                        throw new InvalidArgumentException('The organization owner is already a member.');
                    }

                    if ($actor->isAdmin($lockedOrganization) && ! $actor->isOwner($lockedOrganization)) {
                        if ($existingUser->isAdmin($lockedOrganization) || $existingUser->isOwner($lockedOrganization)) {
                            abort(403);
                        }
                    }

                    $lockedOrganization->users()->syncWithoutDetaching([
                        $existingUser->id => ['role' => $role],
                    ]);

                    $lockedOrganization->users()->updateExistingPivot($existingUser->id, ['role' => $role]);

                    $lockedOrganization->invites()
                        ->whereRaw('lower(email) = ?', [$email])
                        ->delete();

                    return 'Member added to organization.';
                }

                $lockedOrganization->invites()->updateOrCreate(
                    ['email' => $email],
                    [
                        'role' => $role,
                        'token' => (string) Str::uuid(),
                        'created_at' => now(),
                    ]
                );

                return 'Invitation created.';
            });
        } catch (EntitlementLimitExceededException) {
            return redirect()->back()->withErrors([
                'email' => 'Team member limit reached for your current plan.',
            ]);
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withErrors([
                'email' => $exception->getMessage(),
            ]);
        }

        return redirect()->back()->with('status', $statusMessage);
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

    private function currentMemberCount(Organization $organization): int
    {
        $count = (int) $organization->users()->count();

        $ownerIncluded = $organization->users()
            ->where('users.id', $organization->owner_id)
            ->exists();

        return $ownerIncluded ? $count : $count + 1;
    }
}
