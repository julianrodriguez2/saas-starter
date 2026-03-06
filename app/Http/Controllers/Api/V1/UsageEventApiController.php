<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\EntitlementLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsageEventRequest;
use App\Services\UsageRecorder;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class UsageEventApiController extends Controller
{
    public function store(
        StoreUsageEventRequest $request,
        CurrentOrganization $currentOrganization,
        UsageRecorder $usageRecorder
    ): JsonResponse {
        $organization = $currentOrganization->organization;

        if ($organization === null) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context was not found.',
            ], 422);
        }

        $validated = $request->validated();

        try {
            $usageEvent = $usageRecorder->record(
                organization: $organization,
                eventType: $validated['event_type'],
                quantity: $validated['quantity'] ?? 1,
                metadata: [
                    ...($validated['metadata'] ?? []),
                    'source' => 'api.v1.usage-events',
                    'actor_id' => $request->user()->id,
                ]
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
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $usageEvent->id,
                'organization_id' => $usageEvent->organization_id,
                'event_type' => $usageEvent->event_type,
                'quantity' => $usageEvent->quantity,
                'metadata' => $usageEvent->metadata,
                'recorded_at' => $usageEvent->recorded_at?->toIso8601String(),
            ],
        ], 201);
    }
}
