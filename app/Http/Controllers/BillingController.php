<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Services\AuditLogger;
use App\Services\StripePlanSyncService;
use App\Support\AuditActions;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

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
            'hasPaidSubscription' => $organization->hasActivePaidSubscription(),
            'hasStripeCustomer' => filled($organization->stripe_id) || filled($organization->stripe_customer_id),
        ]);
    }

    public function checkout(
        Request $request,
        CurrentOrganization $currentOrganization,
        string $plan,
        AuditLogger $auditLogger
    ): HttpResponse|RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);

        $targetPlan = $this->resolvePaidPlan($plan);

        if ($targetPlan === null || blank($targetPlan->stripe_price_id)) {
            return redirect()->route('billing.index')->withErrors([
                'plan' => 'Invalid paid plan selected.',
            ]);
        }

        if ($organization->hasActivePaidSubscription()) {
            return redirect()->route('billing.index')
                ->with('warning', 'Organization already has an active paid subscription.');
        }

        try {
            $organization->createOrGetStripeCustomer();
        } catch (Throwable $exception) {
            report($exception);

            $message = 'Unable to start checkout right now. Please try again.';

            return redirect()->route('billing.index')
                ->withErrors([
                    'billing' => $message,
                ])
                ->with('error', $message);
        }

        try {
            $organization = DB::transaction(function () use ($organization): Organization {
                $lockedOrganization = Organization::query()
                    ->whereKey($organization->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOrganization->hasActivePaidSubscription()) {
                    throw new RuntimeException('Organization already has an active paid subscription.');
                }

                if (filled($organization->stripe_id)) {
                    $lockedOrganization->stripe_id = $organization->stripe_id;
                }

                $lockedOrganization->stripe_customer_id = $lockedOrganization->stripe_id
                    ?: ($organization->stripe_customer_id ?: $lockedOrganization->stripe_customer_id);
                $lockedOrganization->save();

                return $lockedOrganization;
            });
        } catch (RuntimeException) {
            return redirect()->route('billing.index')
                ->with('warning', 'Organization already has an active paid subscription.');
        }

        $auditLogger->logForOrganization(
            action: AuditActions::BILLING_CHECKOUT_STARTED,
            organization: $organization,
            actor: $request->user(),
            targetType: 'plan',
            targetId: (string) $targetPlan->id,
            metadata: [
                'plan_name' => $targetPlan->name,
                'stripe_price_id' => $targetPlan->stripe_price_id,
            ],
            request: $request
        );

        try {
            return $organization->newSubscription('default', $targetPlan->stripe_price_id)->checkout([
                'success_url' => route('billing.checkout.success'),
                'cancel_url' => route('billing.checkout.cancel'),
                'client_reference_id' => $organization->id,
                'metadata' => [
                    'organization_id' => $organization->id,
                    'target_plan' => $targetPlan->name,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $message = 'Unable to create checkout session right now.';

            return redirect()->route('billing.index')
                ->withErrors([
                    'billing' => $message,
                ])
                ->with('error', $message);
        }
    }

    public function portal(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuditLogger $auditLogger
    ): HttpResponse|RedirectResponse
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

        $auditLogger->logForOrganization(
            action: AuditActions::BILLING_PORTAL_OPENED,
            organization: $organization,
            actor: $request->user(),
            targetType: 'organization',
            targetId: $organization->id,
            request: $request
        );

        try {
            return $organization->redirectToBillingPortal(route('billing.index'));
        } catch (Throwable $exception) {
            report($exception);

            $message = 'Unable to open billing portal right now.';

            return redirect()->route('billing.index')
                ->withErrors([
                    'billing' => $message,
                ])
                ->with('error', $message);
        }
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

}
