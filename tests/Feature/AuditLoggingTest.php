<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_important_actions_create_audit_logs_with_expected_action_names(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());

        $this->actingAsInOrganization($owner, $organization)
            ->post('/settings/api-keys', [
                'name' => 'Audit Key',
            ])
            ->assertRedirect('/settings/api-keys');

        $this->actingAsInOrganization($owner, $organization)
            ->post('/organizations/members/invite', [
                'email' => 'audit.member@example.com',
                'role' => 'member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => AuditActions::API_KEY_CREATED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $organization->id,
            'action' => AuditActions::MEMBER_INVITED,
        ]);
    }

    public function test_audit_log_index_only_returns_current_organization_logs(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organizationA = $this->createOrganizationWithOwner($owner, $this->freePlan(), 'Org A');
        $organizationB = $this->createOrganizationWithOwner(User::factory()->create(), $this->freePlan(), 'Org B');

        AuditLog::factory()->create([
            'organization_id' => $organizationA->id,
            'actor_id' => $owner->id,
            'action' => 'org_a.event',
        ]);

        AuditLog::factory()->create([
            'organization_id' => $organizationB->id,
            'action' => 'org_b.event',
        ]);

        $this->actingAsInOrganization($owner, $organizationA)
            ->get('/audit-logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AuditLogs/Index')
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.action', 'org_a.event')
            );
    }

    public function test_admin_organization_detail_includes_recent_audit_logs(): void
    {
        $this->seedPlans();

        $superAdmin = User::factory()->create([
            'email' => 'platform.admin@example.com',
        ]);
        $this->makeSuperAdmin($superAdmin);

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        AuditLog::factory()->create([
            'organization_id' => $organization->id,
            'action' => AuditActions::ORGANIZATION_CREATED,
        ]);

        $this->actingAs($superAdmin)
            ->get("/admin/organizations/{$organization->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Organizations/Show')
                ->has('organizationDetail.recent_audit_logs')
                ->where('organizationDetail.recent_audit_logs.0.action', AuditActions::ORGANIZATION_CREATED)
            );
    }
}
