<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
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
            $this->assignFreePlan($organization);
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
    }

    public function syncOrganizationFromCashierState(Organization $organization): void
    {
        $subscription = $organization->subscriptions()
            ->whereIn('stripe_status', $this->activeStatuses)
            ->latest('created_at')
            ->first();

        if ($subscription === null) {
            $this->assignFreePlan($organization);
            return;
        }

        $plan = $this->mapPriceIdToPlan($this->resolveSubscriptionPriceId($subscription));

        if ($plan === null) {
            $this->assignFreePlan($organization);
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
    }

    public function assignFreePlan(Organization $organization): void
    {
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
}
