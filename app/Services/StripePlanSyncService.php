<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Support\AuditActions;
use Illuminate\Support\Carbon;

class StripePlanSyncService
{
    /**
     * @var list<string>
     */
    private array $activeStatuses = [
        'trialing',
        'active',
        'past_due',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function mapPriceIdToPlan(?string $stripePriceId): ?Plan
    {
        if ($stripePriceId === null || $stripePriceId === '') {
            return null;
        }

        return Plan::query()
            ->where('stripe_price_id', $stripePriceId)
            ->first();
    }

    public function syncFromStripeSubscriptionPayload(Organization $organization, array $subscriptionPayload): void
    {
        $previousPlanId = $organization->plan_id;
        $status = (string) data_get($subscriptionPayload, 'status', '');
        $stripeSubscriptionId = data_get($subscriptionPayload, 'id');
        $stripePriceId = data_get($subscriptionPayload, 'items.data.0.price.id');
        $trialEnd = data_get($subscriptionPayload, 'trial_end');
        $stripeCustomerId = data_get($subscriptionPayload, 'customer');

        if (is_string($stripeCustomerId) && $stripeCustomerId !== '') {
            $organization->stripe_id = $stripeCustomerId;
            $organization->stripe_customer_id = $stripeCustomerId;
        }

        if (! in_array($status, $this->activeStatuses, true)) {
            $this->syncOrganizationFromCashierState($organization);
            return;
        }

        $plan = $this->mapPriceIdToPlan($stripePriceId);

        if ($plan === null) {
            $this->assignFreePlan($organization, 'stripe.subscription_payload', $previousPlanId, $status);
            return;
        }

        if (blank($organization->stripe_id) && filled($organization->stripe_customer_id)) {
            $organization->stripe_id = $organization->stripe_customer_id;
        }

        $organization->plan()->associate($plan);
        $organization->stripe_customer_id = $organization->stripe_id ?: $organization->stripe_customer_id;
        $organization->stripe_subscription_id = is_string($stripeSubscriptionId) && $stripeSubscriptionId !== ''
            ? $stripeSubscriptionId
            : null;
        $organization->trial_ends_at = is_numeric($trialEnd)
            ? Carbon::createFromTimestamp((int) $trialEnd)
            : null;
        $organization->save();

        $this->logSubscriptionSync(
            organization: $organization,
            source: 'stripe.subscription_payload',
            status: $status,
            previousPlanId: $previousPlanId
        );
    }

    public function syncOrganizationFromCashierState(Organization $organization): void
    {
        $previousPlanId = $organization->plan_id;
        $subscription = $organization->subscriptions()
            ->whereIn('stripe_status', $this->activeStatuses)
            ->latest('created_at')
            ->first();

        if ($subscription === null) {
            $this->assignFreePlan($organization, 'stripe.cashier_state', $previousPlanId, null);
            return;
        }

        $plan = $this->mapPriceIdToPlan($this->resolveSubscriptionPriceId($subscription));

        if ($plan === null) {
            $this->assignFreePlan(
                organization: $organization,
                source: 'stripe.cashier_state',
                previousPlanId: $previousPlanId,
                status: (string) ($subscription->stripe_status ?? null)
            );
            return;
        }

        if (blank($organization->stripe_id) && filled($organization->stripe_customer_id)) {
            $organization->stripe_id = $organization->stripe_customer_id;
        }

        $organization->plan()->associate($plan);
        $organization->stripe_customer_id = $organization->stripe_id ?: $organization->stripe_customer_id;
        $organization->stripe_subscription_id = $subscription->stripe_id;
        $organization->trial_ends_at = $subscription->trial_ends_at;
        $organization->save();

        $this->logSubscriptionSync(
            organization: $organization,
            source: 'stripe.cashier_state',
            status: (string) ($subscription->stripe_status ?? null),
            previousPlanId: $previousPlanId
        );
    }

    public function assignFreePlan(
        Organization $organization,
        string $source = 'stripe.assign_free',
        ?int $previousPlanId = null,
        ?string $status = null
    ): void
    {
        $previousPlanId ??= $organization->plan_id;

        $freePlan = Plan::query()
            ->where('name', 'Free')
            ->first();

        if ($freePlan !== null) {
            $organization->plan()->associate($freePlan);
        } else {
            $organization->plan()->dissociate();
        }

        if (blank($organization->stripe_id) && filled($organization->stripe_customer_id)) {
            $organization->stripe_id = $organization->stripe_customer_id;
        }

        $organization->stripe_customer_id = $organization->stripe_id ?: $organization->stripe_customer_id;
        $organization->stripe_subscription_id = null;
        $organization->trial_ends_at = null;
        $organization->save();

        $this->logSubscriptionSync(
            organization: $organization,
            source: $source,
            status: $status,
            previousPlanId: $previousPlanId
        );
    }

    private function resolveSubscriptionPriceId(mixed $subscription): ?string
    {
        $stripePrice = (string) ($subscription->stripe_price ?? '');

        if ($stripePrice !== '') {
            return $stripePrice;
        }

        if (! method_exists($subscription, 'items')) {
            return null;
        }

        $itemPrice = $subscription->items()->value('stripe_price');

        return is_string($itemPrice) && $itemPrice !== '' ? $itemPrice : null;
    }

    private function logSubscriptionSync(
        Organization $organization,
        string $source,
        ?string $status,
        ?int $previousPlanId
    ): void {
        $this->auditLogger->logForOrganization(
            action: AuditActions::BILLING_SUBSCRIPTION_SYNCED,
            organization: $organization,
            actor: null,
            actorType: 'system',
            targetType: 'subscription',
            targetId: $organization->stripe_subscription_id,
            metadata: [
                'source' => $source,
                'status' => $status,
                'previous_plan_id' => $previousPlanId,
                'new_plan_id' => $organization->plan_id,
            ]
        );
    }
}
