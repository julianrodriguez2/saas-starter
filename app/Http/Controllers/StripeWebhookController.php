<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\StripeWebhookEvent;
use App\Services\StripePlanSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly StripePlanSyncService $stripePlanSyncService
    ) {
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        $eventId = (string) data_get($request->all(), 'id', '');
        $type = (string) data_get($request->all(), 'type', 'unknown');

        $webhookEvent = $this->getOrCreateWebhookEvent($eventId, $type);

        if ($webhookEvent !== null && $webhookEvent->processed_at !== null) {
            return new Response('Webhook already processed.', 200);
        }

        try {
            $response = parent::handleWebhook($request);
        } catch (Throwable $exception) {
            if ($webhookEvent !== null && $webhookEvent->processed_at === null) {
                $webhookEvent->delete();
            }

            throw $exception;
        }

        if ($webhookEvent !== null && $response->getStatusCode() < 400) {
            $webhookEvent->forceFill([
                'type' => $type,
                'processed_at' => now(),
            ])->save();
        }

        return $response;
    }

    protected function handleCustomerSubscriptionCreated(array $payload): HttpResponse
    {
        $response = $this->callParentWebhookHandler(__FUNCTION__, $payload);
        $this->syncFromSubscriptionPayload($payload);

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload): HttpResponse
    {
        $response = $this->callParentWebhookHandler(__FUNCTION__, $payload);
        $this->syncFromSubscriptionPayload($payload);

        return $response;
    }

    protected function handleCustomerSubscriptionDeleted(array $payload): HttpResponse
    {
        $response = $this->callParentWebhookHandler(__FUNCTION__, $payload);
        $this->syncFromSubscriptionPayload($payload);

        return $response;
    }

    protected function handleInvoicePaid(array $payload): HttpResponse
    {
        $response = $this->callParentWebhookHandler(__FUNCTION__, $payload);
        $this->syncFromCashierState($payload);

        return $response;
    }

    protected function handleInvoicePaymentFailed(array $payload): HttpResponse
    {
        $response = $this->callParentWebhookHandler(__FUNCTION__, $payload);
        $this->syncFromCashierState($payload);

        return $response;
    }

    protected function handleCheckoutSessionCompleted(array $payload): HttpResponse
    {
        $response = $this->callParentWebhookHandler(__FUNCTION__, $payload);
        $this->syncFromCashierState($payload);

        return $response;
    }

    private function syncFromSubscriptionPayload(array $payload): void
    {
        $organization = $this->resolveOrganizationFromPayload($payload);

        if ($organization === null) {
            return;
        }

        $subscriptionPayload = data_get($payload, 'data.object');

        if (! is_array($subscriptionPayload)) {
            return;
        }

        $this->stripePlanSyncService->syncFromStripeSubscriptionPayload(
            $organization,
            $subscriptionPayload
        );
    }

    private function syncFromCashierState(array $payload): void
    {
        $organization = $this->resolveOrganizationFromPayload($payload);

        if ($organization === null) {
            return;
        }

        $this->stripePlanSyncService->syncOrganizationFromCashierState($organization);
    }

    private function resolveOrganizationFromPayload(array $payload): ?Organization
    {
        $organizationId = (string) data_get($payload, 'data.object.client_reference_id', '');

        if ($organizationId !== '') {
            $organization = Organization::query()->find($organizationId);

            if ($organization !== null) {
                return $organization;
            }
        }

        $stripeCustomerId = (string) data_get($payload, 'data.object.customer', '');

        if ($stripeCustomerId === '') {
            return null;
        }

        return Organization::query()
            ->where('stripe_id', $stripeCustomerId)
            ->orWhere('stripe_customer_id', $stripeCustomerId)
            ->first();
    }

    private function getOrCreateWebhookEvent(string $eventId, string $type): ?StripeWebhookEvent
    {
        if ($eventId === '') {
            return null;
        }

        try {
            return StripeWebhookEvent::query()->firstOrCreate(
                ['stripe_event_id' => $eventId],
                ['type' => $type]
            );
        } catch (QueryException) {
            return StripeWebhookEvent::query()
                ->where('stripe_event_id', $eventId)
                ->first();
        }
    }

    private function callParentWebhookHandler(string $method, array $payload): HttpResponse
    {
        $parentClass = get_parent_class($this);

        if ($parentClass === false || ! method_exists($parentClass, $method)) {
            return new Response('Webhook Handled', 200);
        }

        return match ($method) {
            'handleCustomerSubscriptionCreated' => parent::handleCustomerSubscriptionCreated($payload),
            'handleCustomerSubscriptionUpdated' => parent::handleCustomerSubscriptionUpdated($payload),
            'handleCustomerSubscriptionDeleted' => parent::handleCustomerSubscriptionDeleted($payload),
            'handleInvoicePaid' => parent::handleInvoicePaid($payload),
            'handleInvoicePaymentFailed' => parent::handleInvoicePaymentFailed($payload),
            'handleCheckoutSessionCompleted' => parent::handleCheckoutSessionCompleted($payload),
            default => new Response('Webhook Handled', 200),
        };
    }
}
