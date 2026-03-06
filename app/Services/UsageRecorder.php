<?php

namespace App\Services;

use App\Exceptions\EntitlementLimitExceededException;
use App\Jobs\ProcessUsageEvent;
use App\Models\Organization;
use App\Models\UsageEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UsageRecorder
{
    /**
     * @var array<string, string>
     */
    private array $meteredFeatureMap = [
        'api_call' => 'api_calls_per_month',
    ];

    public function __construct(
        private readonly UsageAggregator $usageAggregator,
        private readonly EntitlementService $entitlementService
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws EntitlementLimitExceededException
     */
    public function record(
        Organization $organization,
        string $eventType,
        int $quantity = 1,
        array $metadata = []
    ): UsageEvent {
        return $this->recordInternal(
            organization: $organization,
            eventType: $eventType,
            quantity: $quantity,
            metadata: $metadata,
            dispatchProcessingJob: true
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws EntitlementLimitExceededException
     */
    public function recordWithoutDispatch(
        Organization $organization,
        string $eventType,
        int $quantity = 1,
        array $metadata = []
    ): UsageEvent {
        return $this->recordInternal(
            organization: $organization,
            eventType: $eventType,
            quantity: $quantity,
            metadata: $metadata,
            dispatchProcessingJob: false
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws EntitlementLimitExceededException
     */
    private function recordInternal(
        Organization $organization,
        string $eventType,
        int $quantity,
        array $metadata,
        bool $dispatchProcessingJob
    ): UsageEvent {
        $normalizedEventType = Str::lower(trim($eventType));

        if ($normalizedEventType === '') {
            throw new InvalidArgumentException('eventType must be a non-empty string.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('quantity must be greater than zero.');
        }

        $this->enforceMeteredLimit(
            organization: $organization,
            eventType: $normalizedEventType,
            quantity: $quantity
        );

        $usageEvent = DB::transaction(function () use (
            $organization,
            $normalizedEventType,
            $quantity,
            $metadata
        ): UsageEvent {
            $usageEvent = UsageEvent::query()->create([
                'organization_id' => $organization->id,
                'event_type' => $normalizedEventType,
                'quantity' => $quantity,
                'metadata' => $metadata,
                'recorded_at' => now(),
            ]);

            $this->recordAuditLogIfNeeded($organization, $usageEvent, $quantity, $metadata);

            return $usageEvent;
        });

        if ($dispatchProcessingJob) {
            ProcessUsageEvent::dispatch($usageEvent->id);
        }

        return $usageEvent;
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    private function enforceMeteredLimit(Organization $organization, string $eventType, int $quantity): void
    {
        $feature = $this->meteredFeatureMap[$eventType] ?? null;

        if ($feature === null) {
            return;
        }

        $currentMonthlyUsage = $this->usageAggregator->getMonthlyUsage($organization, $eventType);
        $newTotal = $currentMonthlyUsage + $quantity;

        $this->entitlementService->checkLimit($organization, $feature, $newTotal);
    }

    private function recordAuditLogIfNeeded(
        Organization $organization,
        UsageEvent $usageEvent,
        int $quantity,
        array $metadata
    ): void {
        if ($usageEvent->event_type !== 'api_call') {
            return;
        }

        $actorId = is_numeric($metadata['actor_id'] ?? null)
            ? (int) $metadata['actor_id']
            : $organization->owner_id;

        $organization->auditLogs()->create([
            'actor_id' => $actorId,
            'action' => 'usage.recorded',
            'metadata' => [
                'event_type' => $usageEvent->event_type,
                'quantity' => $quantity,
                'usage_event_id' => $usageEvent->id,
            ],
        ]);
    }
}
