<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\DomainEventFailureService;
use App\Services\IdempotencyService;
use App\Services\StripePlanSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class StripeWebhookController extends CashierWebhookController
{
    private const IDEMPOTENCY_SCOPE = 'stripe.webhook';

    public function __construct(
        private readonly StripePlanSyncService $stripePlanSyncService,
        private readonly IdempotencyService $idempotencyService,
        private readonly DomainEventFailureService $domainEventFailureService
    ) {
    }

    public function handleWebhook(Request $request): HttpResponse
    {
        $payload = $request->all();
        $eventId = (string) data_get($payload, 'id', '');
        $eventType = (string) data_get($payload, 'type', 'unknown');
        $fingerprint = $request->getContent() !== ''
            ? hash('sha256', $request->getContent())
            : null;

        try {
            if ($eventId !== '') {
                $this->idempotencyService->acquire(
                    self::IDEMPOTENCY_SCOPE,
                    $eventId,
                    $fingerprint
                );

                if ($this->idempotencyService->alreadyProcessed(self::IDEMPOTENCY_SCOPE, $eventId)) {
                    return new Response('Webhook already processed.', 200);
                }
            }

            $response = parent::handleWebhook($request);

            if ($eventId !== '' && $response->getStatusCode() < 400) {
                $this->idempotencyService->markProcessed(
                    self::IDEMPOTENCY_SCOPE,
                    $eventId,
                    [
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'status_code' => $response->getStatusCode(),
                    ]
                );
            }

            return $response;
        } catch (Throwable $exception) {
            $this->domainEventFailureService->recordFailure(
                source: 'stripe',
                eventKey: $eventId !== '' ? $eventId : null,
                eventType: $eventType,
                payload: is_array($payload) ? $payload : null,
                error: $exception
            );

            throw $exception;
        }
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
