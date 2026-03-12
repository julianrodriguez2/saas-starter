<?php

namespace Tests\Feature;

use App\Models\OrganizationUser;
use App\Models\User;
use App\Support\AdminImpersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class AdminConsoleSuspensionTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $this->seedPlans();

        $superAdmin = User::factory()->create([
            'email' => 'super.admin@example.com',
        ]);
        $this->makeSuperAdmin($superAdmin);

        $this->actingAs($superAdmin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_non_super_admin_cannot_access_admin_dashboard(): void
    {
        $this->seedPlans();

        $user = User::factory()->create();
        config()->set('platform.super_admin_emails', ['different@example.com']);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_super_admin_can_suspend_organization(): void
    {
        $this->seedPlans();

        $superAdmin = User::factory()->create([
            'email' => 'ops@example.com',
        ]);
        $this->makeSuperAdmin($superAdmin);
        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());

        $this->actingAs($superAdmin)
            ->from("/admin/organizations/{$organization->id}")
            ->post("/admin/organizations/{$organization->id}/suspend", [
                'reason' => 'Compliance review',
            ])
            ->assertRedirect("/admin/organizations/{$organization->id}");

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_suspended' => true,
            'suspension_reason' => 'Compliance review',
        ]);
    }

    public function test_suspended_organization_write_actions_are_blocked(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan())->forceFill([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => 'Manual suspension',
        ]);
        $organization->save();

        $this->actingAsInOrganization($owner, $organization)
            ->from('/organizations/members')
            ->post('/organizations/members/invite', [
                'email' => 'blocked@example.com',
                'role' => OrganizationUser::ROLE_MEMBER,
            ])
            ->assertRedirect('/organizations/members')
            ->assertSessionHasErrors('organization');

        $this->assertDatabaseMissing('invites', [
            'organization_id' => $organization->id,
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_super_admin_can_unsuspend_organization(): void
    {
        $this->seedPlans();

        $superAdmin = User::factory()->create([
            'email' => 'ops2@example.com',
        ]);
        $this->makeSuperAdmin($superAdmin);

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan())->forceFill([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => 'Manual suspension',
        ]);
        $organization->save();

        $this->actingAs($superAdmin)
            ->from("/admin/organizations/{$organization->id}")
            ->post("/admin/organizations/{$organization->id}/unsuspend")
            ->assertRedirect("/admin/organizations/{$organization->id}");

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_suspended' => false,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    public function test_impersonation_start_and_stop_updates_session_state(): void
    {
        $this->seedPlans();

        $superAdmin = User::factory()->create([
            'email' => 'support@example.com',
        ]);
        $this->makeSuperAdmin($superAdmin);

        $originalOrganization = $this->createOrganizationWithOwner(
            User::factory()->create(),
            $this->freePlan(),
            'Original Org'
        );
        $targetOrganization = $this->createOrganizationWithOwner(
            User::factory()->create(),
            $this->proPlan(),
            'Target Org'
        );

        $this->actingAs($superAdmin)
            ->withSession(['organization_id' => $originalOrganization->id])
            ->post("/admin/organizations/{$targetOrganization->id}/impersonate")
            ->assertRedirect('/dashboard')
            ->assertSessionHas(AdminImpersonation::IMPERSONATING_ADMIN_ID, $superAdmin->id)
            ->assertSessionHas(AdminImpersonation::IMPERSONATED_ORGANIZATION_ID, $targetOrganization->id)
            ->assertSessionHas('organization_id', $targetOrganization->id);

        $this
            ->post('/admin/impersonation/stop')
            ->assertRedirect('/admin/organizations')
            ->assertSessionMissing(AdminImpersonation::IMPERSONATING_ADMIN_ID)
            ->assertSessionMissing(AdminImpersonation::IMPERSONATED_ORGANIZATION_ID)
            ->assertSessionHas('organization_id', $originalOrganization->id);
    }
}
