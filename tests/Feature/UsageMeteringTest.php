<?php

namespace Tests\Feature;

use App\Exceptions\EntitlementLimitExceededException;
use App\Models\OrganizationUser;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\UsageAggregator;
use App\Services\UsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class UsageMeteringTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_usage_recorder_creates_usage_event(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $recorder = app(UsageRecorder::class);

        $usageEvent = $recorder->recordWithoutDispatch(
            organization: $organization,
            eventType: 'api_call',
            quantity: 2,
            metadata: ['source' => 'test']
        );

        $this->assertDatabaseHas('usage_events', [
            'id' => $usageEvent->id,
            'organization_id' => $organization->id,
            'event_type' => 'api_call',
            'quantity' => 2,
        ]);
    }

    public function test_usage_aggregator_returns_correct_monthly_totals(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $month = Carbon::now()->startOfMonth()->addDay();

        UsageEvent::factory()->create([
            'organization_id' => $organization->id,
            'event_type' => 'api_call',
            'quantity' => 3,
            'recorded_at' => $month,
        ]);

        UsageEvent::factory()->create([
            'organization_id' => $organization->id,
            'event_type' => 'api_call',
            'quantity' => 2,
            'recorded_at' => $month->copy()->addDay(),
        ]);

        $aggregator = app(UsageAggregator::class);
        $total = $aggregator->getMonthlyUsage($organization, 'api_call', Carbon::now());

        $this->assertSame(5, $total);
    }

    public function test_usage_with_same_idempotency_key_is_not_double_counted(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $recorder = app(UsageRecorder::class);

        $first = $recorder->recordWithoutDispatch(
            organization: $organization,
            eventType: 'api_call',
            quantity: 1,
            metadata: ['source' => 'test'],
            idempotencyKey: 'idem-key-1'
        );

        $second = $recorder->recordWithoutDispatch(
            organization: $organization,
            eventType: 'api_call',
            quantity: 1,
            metadata: ['source' => 'test'],
            idempotencyKey: 'idem-key-1'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, UsageEvent::query()->where('organization_id', $organization->id)->count());
    }

    public function test_api_call_usage_respects_monthly_plan_limit(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());

        UsageEvent::factory()->create([
            'organization_id' => $organization->id,
            'event_type' => 'api_call',
            'quantity' => 1000,
            'recorded_at' => now(),
        ]);

        $recorder = app(UsageRecorder::class);

        $this->expectException(EntitlementLimitExceededException::class);

        $recorder->recordWithoutDispatch(
            organization: $organization,
            eventType: 'api_call',
            quantity: 1,
            metadata: ['source' => 'test']
        );
    }

    public function test_suspended_organization_cannot_record_usage_through_protected_write_action(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan())->forceFill([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => 'Suspended for test',
        ]);
        $organization->save();

        $this->actingAsInOrganization($owner, $organization)
            ->from('/usage')
            ->post('/usage/test-record')
            ->assertRedirect('/usage')
            ->assertSessionHasErrors('organization');

        $this->assertDatabaseCount('usage_events', 0);
    }

    public function test_usage_dashboard_endpoint_returns_expected_data_for_authenticated_member(): void
    {
        $this->seedPlans();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $this->createOrganizationWithOwner($owner, $this->freePlan());
        $this->addOrganizationMember($organization, $member, OrganizationUser::ROLE_MEMBER);

        UsageEvent::factory()->create([
            'organization_id' => $organization->id,
            'event_type' => 'api_call',
            'quantity' => 7,
            'recorded_at' => now(),
        ]);

        $this->actingAsInOrganization($member, $organization)
            ->get('/usage')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Usage/Index')
                ->where('usageOrganization.id', $organization->id)
                ->where('apiCallSummary.used', 7)
            );
    }
}
