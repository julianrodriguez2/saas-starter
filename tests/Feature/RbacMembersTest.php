<?php

namespace Tests\Feature;

use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class RbacMembersTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_owner_can_invite_member(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());

        $this->actingAsInOrganization($owner, $organization)
            ->post('/organizations/members/invite', [
                'email' => 'new.member@example.com',
                'role' => OrganizationUser::ROLE_MEMBER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invites', [
            'organization_id' => $organization->id,
            'email' => 'new.member@example.com',
            'role' => OrganizationUser::ROLE_MEMBER,
        ]);
    }

    public function test_admin_can_invite_member(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());
        $this->addOrganizationMember($organization, $admin, OrganizationUser::ROLE_ADMIN);

        $this->actingAsInOrganization($admin, $organization)
            ->post('/organizations/members/invite', [
                'email' => 'invited.by.admin@example.com',
                'role' => OrganizationUser::ROLE_MEMBER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invites', [
            'organization_id' => $organization->id,
            'email' => 'invited.by.admin@example.com',
            'role' => OrganizationUser::ROLE_MEMBER,
        ]);
    }

    public function test_member_cannot_access_members_management(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());
        $this->addOrganizationMember($organization, $member, OrganizationUser::ROLE_MEMBER);

        $this->actingAsInOrganization($member, $organization)
            ->get('/organizations/members')
            ->assertForbidden();
    }

    public function test_admin_cannot_remove_owner(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());
        $this->addOrganizationMember($organization, $admin, OrganizationUser::ROLE_ADMIN);

        $this->actingAsInOrganization($admin, $organization)
            ->delete("/organizations/members/{$owner->id}")
            ->assertForbidden();
    }

    public function test_owner_can_change_member_role_between_admin_and_member(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());
        $this->addOrganizationMember($organization, $member, OrganizationUser::ROLE_MEMBER);

        $this->actingAsInOrganization($owner, $organization)
            ->patch("/organizations/members/{$member->id}/role", [
                'role' => OrganizationUser::ROLE_ADMIN,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => OrganizationUser::ROLE_ADMIN,
        ]);

        $this->actingAsInOrganization($owner, $organization)
            ->patch("/organizations/members/{$member->id}/role", [
                'role' => OrganizationUser::ROLE_MEMBER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => OrganizationUser::ROLE_MEMBER,
        ]);
    }

    public function test_team_member_limit_is_enforced_by_plan(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());

        $existingMemberA = User::factory()->create();
        $existingMemberB = User::factory()->create();
        $this->addOrganizationMember($organization, $existingMemberA, OrganizationUser::ROLE_MEMBER);
        $this->addOrganizationMember($organization, $existingMemberB, OrganizationUser::ROLE_MEMBER);

        $this->actingAsInOrganization($owner, $organization)
            ->from('/organizations/members')
            ->post('/organizations/members/invite', [
                'email' => 'limit.exceeded@example.com',
                'role' => OrganizationUser::ROLE_MEMBER,
            ])
            ->assertRedirect('/organizations/members')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('invites', [
            'organization_id' => $organization->id,
            'email' => 'limit.exceeded@example.com',
        ]);
    }
}
