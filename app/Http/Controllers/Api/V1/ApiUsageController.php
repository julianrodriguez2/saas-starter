<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\EntitlementLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiUsageEventRequest;
use App\Services\DomainEventFailureService;
use App\Services\UsageRecorder;
use App\Support\CurrentApiOrganization;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Throwable;

class ApiUsageController extends Controller
{
    public function store(
        StoreApiUsageEventRequest $request,
        CurrentApiOrganization $currentApiOrganization,
        UsageRecorder $usageRecorder,
        DomainEventFailureService $domainEventFailureService
    ): JsonResponse {
        $organization = $currentApiOrganization->organization;
        $apiKey = $currentApiOrganization->apiKey;
        $validated = $request->validated();

        try {
            $usageEvent = $usageRecorder->record(
                organization: $organization,
                eventType: $validated['event_type'],
                quantity: (int) $validated['quantity'],
                metadata: [
                    ...($validated['metadata'] ?? []),
                    'source' => 'api.v1.usage-events',
                    'api_key_id' => $apiKey->id,
                ],
                idempotencyKey: $validated['idempotency_key'] ?? null
            );
        } catch (EntitlementLimitExceededException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Usage limit exceeded.',
                'error' => [
                    'feature' => $exception->feature,
                    'current_value' => $exception->currentValue,
                    'limit' => $exception->limit,
                ],
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            $domainEventFailureService->recordFailure(
                source: 'internal_api',
                eventKey: $validated['idempotency_key'] ?? null,
                eventType: $validated['event_type'] ?? null,
                payload: [
                    ...$validated,
                    'organization_id' => $organization->id,
                    'api_key_id' => $apiKey->id,
                ],
                error: $exception
            );

            return response()->json([
                'success' => false,
                'message' => 'Usage event processing failed.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'usage_event_id' => $usageEvent->id,
            'event_type' => $usageEvent->event_type,
            'quantity' => $usageEvent->quantity,
        ], 201);
    }
}
