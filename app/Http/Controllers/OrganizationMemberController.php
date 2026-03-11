<?php

namespace App\Http\Controllers;

use App\Exceptions\EntitlementLimitExceededException;
use App\Http\Requests\Organization\InviteOrganizationMemberRequest;
use App\Http\Requests\Organization\UpdateOrganizationMemberRoleRequest;
use App\Models\Invite;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EntitlementService;
use App\Services\PlatformCacheService;
use App\Support\AuditActions;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class OrganizationMemberController extends Controller
{
    public function index(
        Request $request,
        CurrentOrganization $currentOrganization,
        PlatformCacheService $platformCacheService
    ): Response
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

        $memberCount = $platformCacheService->rememberOrganizationMemberCount(
            $organization->id,
            fn (): int => $this->currentMemberCount($organization)
        );

        return Inertia::render('Organizations/Members', [
            'membersOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'memberCount' => $memberCount,
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
        InviteOrganizationMemberRequest $request,
        CurrentOrganization $currentOrganization,
        EntitlementService $entitlementService,
        AuditLogger $auditLogger,
        PlatformCacheService $platformCacheService
    ): RedirectResponse
    {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        $validated = $request->validated();

        $actor = $request->user();
        $email = Str::lower($validated['email']);
        $role = $validated['role'];

        if ($actor->isAdmin($organization) && ! $actor->isOwner($organization) && $role === OrganizationUser::ROLE_ADMIN) {
            abort(403);
        }

        try {
            $inviteOutcome = DB::transaction(function () use (
                $organization,
                $actor,
                $email,
                $role,
                $entitlementService
            ): array {
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

                    return [
                        'message' => 'Member added to organization.',
                        'target_type' => 'user',
                        'target_id' => (string) $existingUser->id,
                        'user_id' => $existingUser->id,
                        'email' => $existingUser->email,
                        'role' => $role,
                    ];
                }

                $invite = $lockedOrganization->invites()->updateOrCreate(
                    ['email' => $email],
                    [
                        'role' => $role,
                        'token' => (string) Str::uuid(),
                        'created_at' => now(),
                    ]
                );

                return [
                    'message' => 'Invitation created.',
                    'target_type' => 'invite',
                    'target_id' => (string) $invite->id,
                    'user_id' => null,
                    'email' => $email,
                    'role' => $role,
                ];
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

        $auditLogger->logForOrganization(
            action: AuditActions::MEMBER_INVITED,
            organization: $organization,
            actor: $actor,
            targetType: $inviteOutcome['target_type'],
            targetId: $inviteOutcome['target_id'],
            metadata: [
                'email' => $inviteOutcome['email'],
                'role' => $inviteOutcome['role'],
            ],
            request: $request
        );

        $platformCacheService->forgetOrganization($organization->id);
        $platformCacheService->forgetOrganizationAccess((int) $actor->id, $organization->id);
        $platformCacheService->forgetUserOrganizations((int) $actor->id);

        if (is_numeric($inviteOutcome['user_id'] ?? null)) {
            $targetUserId = (int) $inviteOutcome['user_id'];
            $platformCacheService->forgetOrganizationAccess($targetUserId, $organization->id);
            $platformCacheService->forgetUserOrganizations($targetUserId);
        }

        return redirect()->back()->with('status', $inviteOutcome['message']);
    }

    public function destroy(
        Request $request,
        CurrentOrganization $currentOrganization,
        User $user,
        AuditLogger $auditLogger,
        PlatformCacheService $platformCacheService
    ): RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        if (! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        if ($user->isOwner($organization)) {
            abort(403);
        }

        $actor = $request->user();
        $removedRole = $user->roleInOrganization($organization);

        if ($actor->isOwner($organization)) {
            $organization->users()->detach($user->id);

            $auditLogger->logForOrganization(
                action: AuditActions::MEMBER_REMOVED,
                organization: $organization,
                actor: $actor,
                targetType: 'user',
                targetId: (string) $user->id,
                metadata: [
                    'removed_role' => $removedRole,
                ],
                request: $request
            );

            $platformCacheService->forgetOrganization($organization->id);
            $platformCacheService->forgetOrganizationAccess((int) $actor->id, $organization->id);
            $platformCacheService->forgetOrganizationAccess($user->id, $organization->id);
            $platformCacheService->forgetUserOrganizations((int) $actor->id);
            $platformCacheService->forgetUserOrganizations($user->id);

            return redirect()->back()->with('status', 'Member removed.');
        }

        if ($actor->isAdmin($organization)) {
            if ($user->isAdmin($organization)) {
                abort(403);
            }

            $organization->users()->detach($user->id);

            $auditLogger->logForOrganization(
                action: AuditActions::MEMBER_REMOVED,
                organization: $organization,
                actor: $actor,
                targetType: 'user',
                targetId: (string) $user->id,
                metadata: [
                    'removed_role' => $removedRole,
                ],
                request: $request
            );

            $platformCacheService->forgetOrganization($organization->id);
            $platformCacheService->forgetOrganizationAccess((int) $actor->id, $organization->id);
            $platformCacheService->forgetOrganizationAccess($user->id, $organization->id);
            $platformCacheService->forgetUserOrganizations((int) $actor->id);
            $platformCacheService->forgetUserOrganizations($user->id);

            return redirect()->back()->with('status', 'Member removed.');
        }

        abort(403);
    }

    public function updateRole(
        UpdateOrganizationMemberRoleRequest $request,
        CurrentOrganization $currentOrganization,
        User $user,
        AuditLogger $auditLogger,
        PlatformCacheService $platformCacheService
    ): RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('manageMembers', $organization);

        $validated = $request->validated();

        if (! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        if ($user->isOwner($organization)) {
            abort(403);
        }

        $actor = $request->user();
        $newRole = $validated['role'];
        $previousRole = $user->roleInOrganization($organization);

        if ($actor->isOwner($organization)) {
            $organization->users()->updateExistingPivot($user->id, ['role' => $newRole]);

            $auditLogger->logForOrganization(
                action: AuditActions::MEMBER_ROLE_UPDATED,
                organization: $organization,
                actor: $actor,
                targetType: 'user',
                targetId: (string) $user->id,
                metadata: [
                    'previous_role' => $previousRole,
                    'new_role' => $newRole,
                ],
                request: $request
            );

            $platformCacheService->forgetOrganization($organization->id);
            $platformCacheService->forgetOrganizationAccess((int) $actor->id, $organization->id);
            $platformCacheService->forgetOrganizationAccess($user->id, $organization->id);
            $platformCacheService->forgetUserOrganizations((int) $actor->id);
            $platformCacheService->forgetUserOrganizations($user->id);

            return redirect()->back()->with('status', 'Member role updated.');
        }

        if ($actor->isAdmin($organization)) {
            if ($newRole !== OrganizationUser::ROLE_MEMBER || $user->isAdmin($organization)) {
                abort(403);
            }

            $organization->users()->updateExistingPivot($user->id, ['role' => OrganizationUser::ROLE_MEMBER]);

            $auditLogger->logForOrganization(
                action: AuditActions::MEMBER_ROLE_UPDATED,
                organization: $organization,
                actor: $actor,
                targetType: 'user',
                targetId: (string) $user->id,
                metadata: [
                    'previous_role' => $previousRole,
                    'new_role' => OrganizationUser::ROLE_MEMBER,
                ],
                request: $request
            );

            $platformCacheService->forgetOrganization($organization->id);
            $platformCacheService->forgetOrganizationAccess((int) $actor->id, $organization->id);
            $platformCacheService->forgetOrganizationAccess($user->id, $organization->id);
            $platformCacheService->forgetUserOrganizations((int) $actor->id);
            $platformCacheService->forgetUserOrganizations($user->id);

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
