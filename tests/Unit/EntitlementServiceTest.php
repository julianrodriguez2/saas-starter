<?php

namespace Tests\Unit;

use App\Exceptions\EntitlementLimitExceededException;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_new_organization_defaults_to_free_plan(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(User::factory()->create());

        $this->assertNotNull($organization->plan_id);
        $this->assertSame('free', strtolower((string) $organization->plan()->value('name')));
    }

    public function test_entitlement_service_returns_expected_limit_for_free_plan(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(
            plan: $this->freePlan()
        );

        $service = app(EntitlementService::class);

        $this->assertSame(3, $service->getLimit($organization, 'team_members'));
        $this->assertSame(1000, $service->getLimit($organization, 'api_calls_per_month'));
    }

    public function test_has_feature_uses_boolean_limits_correctly(): void
    {
        $this->seedPlans();
        $service = app(EntitlementService::class);

        $freeOrganization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $proOrganization = $this->createOrganizationWithOwner(plan: $this->proPlan());

        $this->assertFalse($service->hasFeature($freeOrganization, 'advanced_features'));
        $this->assertTrue($service->hasFeature($proOrganization, 'advanced_features'));
    }

    public function test_check_limit_allows_values_within_limit(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(EntitlementService::class);

        $service->checkLimit($organization, 'team_members', 3);

        $this->assertTrue(true);
    }

    public function test_check_limit_rejects_values_over_limit(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $service = app(EntitlementService::class);

        $this->expectException(EntitlementLimitExceededException::class);

        $service->checkLimit($organization, 'team_members', 4);
    }

    public function test_enterprise_null_limits_behave_as_unlimited(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->enterprisePlan());
        $service = app(EntitlementService::class);

        $this->assertNull($service->getLimit($organization, 'api_calls_per_month'));
        $this->assertTrue($service->hasFeature($organization, 'api_calls_per_month'));

        $service->checkLimit($organization, 'api_calls_per_month', 9999999);

        $this->assertTrue(true);
    }
}
