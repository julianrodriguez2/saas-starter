<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Services\StripePlanSyncService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller
{
    public function index(
        CurrentOrganization $currentOrganization,
        StripePlanSyncService $stripePlanSyncService
    ): Response {
        $organization = $this->resolveOrganization($currentOrganization)->load('plan');
        $subscription = $organization->subscriptions()
            ->latest('created_at')
            ->first();
        $subscriptionPriceId = $this->resolveSubscriptionPriceId($subscription);
        $stripeMappedPlan = $stripePlanSyncService->mapPriceIdToPlan($subscriptionPriceId);
        $subscriptionStatus = $this->normalizeSubscriptionStatus($subscription);

        return Inertia::render('Billing/Index', [
            'billingOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'localPlan' => [
                'id' => $organization->plan?->id,
                'name' => $organization->plan?->name ?? 'Free',
                'limits' => $organization->plan?->limits ?? [],
            ],
            'subscription' => [
                'status' => $subscriptionStatus,
                'name' => $subscription?->name,
                'stripe_plan_name' => $stripeMappedPlan?->name,
                'stripe_price_id' => $subscriptionPriceId,
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String()
                    ?? $organization->trial_ends_at?->toIso8601String(),
            ],
            'availablePlans' => $this->resolvePaidPlans()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => Str::lower($plan->name),
                    'stripe_price_id' => $plan->stripe_price_id,
                    'limits' => $plan->limits ?? [],
                ])
                ->values()
                ->all(),
            'hasPaidSubscription' => in_array($subscriptionStatus, ['trialing', 'active', 'past_due'], true),
            'hasStripeCustomer' => filled($organization->stripe_id) || filled($organization->stripe_customer_id),
        ]);
    }

    public function checkout(
        CurrentOrganization $currentOrganization,
        string $plan
    ): HttpResponse|RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);
        $targetPlan = $this->resolvePaidPlan($plan);

        if ($targetPlan === null || blank($targetPlan->stripe_price_id)) {
            return redirect()->route('billing.index')->withErrors([
                'plan' => 'Invalid paid plan selected.',
            ]);
        }

        if ($this->hasPaidSubscription($organization)) {
            return redirect()->route('billing.index')
                ->with('warning', 'Organization already has an active paid subscription.');
        }

        $organization->createOrGetStripeCustomer();
        $organization->stripe_customer_id = $organization->stripe_id ?? $organization->stripe_customer_id;
        $organization->save();

        return $organization->newSubscription('default', $targetPlan->stripe_price_id)->checkout([
            'success_url' => route('billing.checkout.success'),
            'cancel_url' => route('billing.checkout.cancel'),
            'client_reference_id' => $organization->id,
            'metadata' => [
                'organization_id' => $organization->id,
                'target_plan' => $targetPlan->name,
            ],
        ]);
    }

    public function portal(CurrentOrganization $currentOrganization): HttpResponse|RedirectResponse
    {
        $organization = $this->resolveOrganization($currentOrganization);

        if (blank($organization->stripe_id) && filled($organization->stripe_customer_id)) {
            $organization->stripe_id = $organization->stripe_customer_id;
            $organization->save();
        }

        if (blank($organization->stripe_id)) {
            return redirect()->route('billing.index')
                ->with('error', 'No Stripe customer found for this organization.');
        }

        return $organization->redirectToBillingPortal(route('billing.index'));
    }

    public function checkoutSuccess(): RedirectResponse
    {
        return redirect()->route('billing.index')
            ->with('success', 'Checkout completed. Subscription is now syncing.');
    }

    public function checkoutCancel(): RedirectResponse
    {
        return redirect()->route('billing.index')
            ->with('warning', 'Checkout canceled.');
    }

    private function resolveOrganization(CurrentOrganization $currentOrganization): Organization
    {
        $organization = $currentOrganization->organization;

        abort_if($organization === null, 404);

        return $organization;
    }

    private function resolvePaidPlan(string $planKey): ?Plan
    {
        $planKey = Str::lower($planKey);

        if (! in_array($planKey, ['pro', 'enterprise'], true)) {
            return null;
        }

        return Plan::query()
            ->whereRaw('lower(name) = ?', [$planKey])
            ->whereNotNull('stripe_price_id')
            ->first();
    }

    private function normalizeSubscriptionStatus(mixed $subscription): string
    {
        if ($subscription === null) {
            return 'free';
        }

        $status = (string) ($subscription->stripe_status ?? 'free');

        if (in_array($status, ['trialing', 'active', 'past_due', 'canceled'], true)) {
            return $status;
        }

        if (method_exists($subscription, 'canceled') && $subscription->canceled()) {
            return 'canceled';
        }

        return $status;
    }

    private function resolveSubscriptionPriceId(mixed $subscription): ?string
    {
        if ($subscription === null) {
            return null;
        }

        $priceId = (string) ($subscription->stripe_price ?? '');

        if ($priceId !== '') {
            return $priceId;
        }

        if (! method_exists($subscription, 'items')) {
            return null;
        }

        $itemPriceId = $subscription->items()->value('stripe_price');

        return is_string($itemPriceId) && $itemPriceId !== '' ? $itemPriceId : null;
    }

    /**
     * @return Collection<int, Plan>
     */
    private function resolvePaidPlans(): Collection
    {
        return Plan::query()
            ->whereRaw('lower(name) in (?, ?)', ['pro', 'enterprise'])
            ->orderByRaw("case lower(name) when 'pro' then 1 when 'enterprise' then 2 else 3 end")
            ->get();
    }

    private function hasPaidSubscription(Organization $organization): bool
    {
        return $organization->subscriptions()
            ->whereIn('stripe_status', ['trialing', 'active', 'past_due'])
            ->exists();
    }
}
