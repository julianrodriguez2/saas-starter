<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class AuthOrganizationProvisioningTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_registration_creates_organization_automatically(): void
    {
        $this->seedPlans();

        $this->post('/register', [
            'name' => 'Alice Founder',
            'email' => 'alice@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $this->assertDatabaseHas('organizations', [
            'name' => "Alice Founder's Organization",
            'owner_id' => $user->id,
        ]);
    }

    public function test_registering_user_is_attached_as_organization_owner(): void
    {
        $this->seedPlans();

        $this->post('/register', [
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'bob@example.com')->firstOrFail();
        $organization = Organization::query()
            ->where('owner_id', $user->id)
            ->firstOrFail();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationUser::ROLE_OWNER,
        ]);
    }

    public function test_current_organization_is_stored_and_resolved_after_registration(): void
    {
        $this->seedPlans();

        $response = $this->post('/register', [
            'name' => 'Cara Admin',
            'email' => 'cara@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::query()->where('email', 'cara@example.com')->firstOrFail();
        $organization = Organization::query()
            ->where('owner_id', $user->id)
            ->firstOrFail();

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('organization_id', $organization->id);

        $this->actingAs($user)
            ->withSession(['organization_id' => $organization->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('organization.current.id', $organization->id)
                ->where('organization.current.name', $organization->name)
            );
    }

    public function test_organization_switching_works_for_memberships_user_has(): void
    {
        $this->seedPlans();

        $user = User::factory()->create();
        $currentOrganization = $this->createOrganizationWithOwner($user, $this->freePlan(), 'Alpha Org');
        $targetOrganization = $this->createOrganizationWithOwner(
            User::factory()->create(),
            $this->proPlan(),
            'Beta Org'
        );
        $this->addOrganizationMember($targetOrganization, $user, OrganizationUser::ROLE_ADMIN);

        $response = $this->actingAsInOrganization($user, $currentOrganization)
            ->post('/organizations/switch', [
                'organization_id' => $targetOrganization->id,
            ]);

        $response->assertRedirect()
            ->assertSessionHas('organization_id', $targetOrganization->id);
    }

    public function test_unauthorized_organization_switch_is_blocked(): void
    {
        $this->seedPlans();

        $user = User::factory()->create();
        $currentOrganization = $this->createOrganizationWithOwner($user, $this->freePlan(), 'Gamma Org');
        $forbiddenOrganization = $this->createOrganizationWithOwner(
            User::factory()->create(),
            $this->proPlan(),
            'Restricted Org'
        );

        $response = $this->actingAsInOrganization($user, $currentOrganization)
            ->post('/organizations/switch', [
                'organization_id' => $forbiddenOrganization->id,
            ]);

        $response->assertForbidden()
            ->assertSessionHas('organization_id', $currentOrganization->id);
    }
}
