<?php

namespace Tests\Feature;

use App\Services\StripePlanSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Tests\Concerns\InteractsWithTenantTestData;
use Tests\TestCase;

class BillingPlanSyncTest extends TestCase
{
    use InteractsWithTenantTestData;
    use RefreshDatabase;

    public function test_stripe_plan_sync_service_maps_pro_price_id_to_pro_plan(): void
    {
        $this->seedPlans();

        $service = app(StripePlanSyncService::class);
        $plan = $service->mapPriceIdToPlan('price_pro_placeholder');

        $this->assertNotNull($plan);
        $this->assertSame('pro', strtolower($plan->name));
    }

    public function test_stripe_plan_sync_service_maps_enterprise_price_id_to_enterprise_plan(): void
    {
        $this->seedPlans();

        $service = app(StripePlanSyncService::class);
        $plan = $service->mapPriceIdToPlan('price_enterprise_placeholder');

        $this->assertNotNull($plan);
        $this->assertSame('enterprise', strtolower($plan->name));
    }

    public function test_canceled_subscription_sync_reverts_organization_to_free_plan(): void
    {
        $this->seedPlans();

        $organization = $this->createOrganizationWithOwner(plan: $this->proPlan());
        $service = app(StripePlanSyncService::class);

        $service->syncFromStripeSubscriptionPayload($organization, [
            'id' => 'sub_canceled',
            'status' => 'canceled',
            'customer' => 'cus_test_123',
            'items' => [
                'data' => [
                    [
                        'price' => ['id' => 'price_pro_placeholder'],
                    ],
                ],
            ],
        ]);

        $organization->refresh();

        $this->assertSame('free', strtolower((string) $organization->plan()->value('name')));
        $this->assertNull($organization->stripe_subscription_id);
    }

    public function test_webhook_processing_stores_idempotency_marker(): void
    {
        $this->seedPlans();
        $this->withoutMiddleware(VerifyWebhookSignature::class);

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $payload = $this->subscriptionWebhookPayload(
            eventId: 'evt_plan_sync_once',
            organizationId: $organization->id
        );

        $this->postJson('/stripe/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('idempotency_keys', [
            'scope' => 'stripe.webhook',
            'key' => 'evt_plan_sync_once',
        ]);

        $this->assertNotNull(
            \App\Models\IdempotencyKey::query()
                ->where('scope', 'stripe.webhook')
                ->where('key', 'evt_plan_sync_once')
                ->value('processed_at')
        );

        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_duplicate_webhook_event_does_not_double_process(): void
    {
        $this->seedPlans();
        $this->withoutMiddleware(VerifyWebhookSignature::class);

        $organization = $this->createOrganizationWithOwner(plan: $this->freePlan());
        $payload = $this->subscriptionWebhookPayload(
            eventId: 'evt_plan_sync_duplicate',
            organizationId: $organization->id
        );

        $this->postJson('/stripe/webhook', $payload)->assertOk();
        $this->postJson('/stripe/webhook', $payload)->assertOk();

        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertNotNull(
            \App\Models\IdempotencyKey::query()
                ->where('scope', 'stripe.webhook')
                ->where('key', 'evt_plan_sync_duplicate')
                ->value('processed_at')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionWebhookPayload(
        string $eventId,
        string $organizationId
    ): array {
        return [
            'id' => $eventId,
            'type' => 'ping.event',
            'data' => [
                'object' => [
                    'client_reference_id' => $organizationId,
                ],
            ],
        ];
    }
}
