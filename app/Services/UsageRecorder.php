<?php

namespace App\Services;

use App\Exceptions\EntitlementLimitExceededException;
use App\Jobs\ProcessUsageEvent;
use App\Models\IdempotencyKey;
use App\Models\Organization;
use App\Models\UsageEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class UsageRecorder
{
    private const IDEMPOTENCY_SCOPE = 'usage.record';

    /**
     * @var array<string, string>
     */
    private array $meteredFeatureMap = [
        'api_call' => 'api_calls_per_month',
    ];

    public function __construct(
        private readonly UsageAggregator $usageAggregator,
        private readonly EntitlementService $entitlementService,
        private readonly IdempotencyService $idempotencyService
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
        array $metadata = [],
        ?string $idempotencyKey = null
    ): UsageEvent {
        return $this->recordInternal(
            organization: $organization,
            eventType: $eventType,
            quantity: $quantity,
            metadata: $metadata,
            dispatchProcessingJob: true,
            idempotencyKey: $idempotencyKey
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
        array $metadata = [],
        ?string $idempotencyKey = null
    ): UsageEvent {
        return $this->recordInternal(
            organization: $organization,
            eventType: $eventType,
            quantity: $quantity,
            metadata: $metadata,
            dispatchProcessingJob: false,
            idempotencyKey: $idempotencyKey
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
        bool $dispatchProcessingJob,
        ?string $idempotencyKey
    ): UsageEvent {
        $normalizedEventType = Str::lower(trim($eventType));
        $normalizedIdempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $metadata = $this->withIdempotencyMetadata($metadata, $normalizedIdempotencyKey);

        if ($normalizedEventType === '') {
            throw new InvalidArgumentException('eventType must be a non-empty string.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('quantity must be greater than zero.');
        }

        if ($normalizedIdempotencyKey !== null) {
            $usageEvent = $this->recordWithIdempotency(
                organization: $organization,
                eventType: $normalizedEventType,
                quantity: $quantity,
                metadata: $metadata,
                idempotencyKey: $normalizedIdempotencyKey
            );
        } else {
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
        }

        if ($dispatchProcessingJob) {
            ProcessUsageEvent::dispatch($usageEvent->id);
        }

        return $usageEvent;
    }

    private function recordWithIdempotency(
        Organization $organization,
        string $eventType,
        int $quantity,
        array $metadata,
        string $idempotencyKey
    ): UsageEvent {
        $serviceIdempotencyKey = $this->serviceIdempotencyKey($organization, $idempotencyKey);
        $fingerprint = $this->buildFingerprint($organization, $eventType, $quantity, $metadata);

        $this->idempotencyService->acquire(
            self::IDEMPOTENCY_SCOPE,
            $serviceIdempotencyKey,
            $fingerprint
        );

        return DB::transaction(function () use (
            $organization,
            $eventType,
            $quantity,
            $metadata,
            $idempotencyKey,
            $serviceIdempotencyKey
        ): UsageEvent {
            $idempotencyRow = IdempotencyKey::query()
                ->where('scope', self::IDEMPOTENCY_SCOPE)
                ->where('key', $serviceIdempotencyKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($idempotencyRow->processed_at !== null) {
                $existingUsageEvent = $this->resolveIdempotentUsageEvent(
                    $organization,
                    $serviceIdempotencyKey,
                    $idempotencyKey
                );

                if ($existingUsageEvent !== null) {
                    return $existingUsageEvent;
                }

                throw new RuntimeException(
                    "Usage event already marked processed for idempotency key [{$idempotencyKey}], but no matching event was found."
                );
            }

            $this->enforceMeteredLimit(
                organization: $organization,
                eventType: $eventType,
                quantity: $quantity
            );

            $usageEvent = UsageEvent::query()->create([
                'organization_id' => $organization->id,
                'event_type' => $eventType,
                'quantity' => $quantity,
                'metadata' => $metadata,
                'recorded_at' => now(),
            ]);

            $this->recordAuditLogIfNeeded($organization, $usageEvent, $quantity, $metadata);

            $idempotencyRow->processed_at = now();
            $idempotencyRow->response_payload = [
                'usage_event_id' => $usageEvent->id,
                'organization_id' => $usageEvent->organization_id,
                'event_type' => $usageEvent->event_type,
                'quantity' => $usageEvent->quantity,
            ];
            $idempotencyRow->save();

            return $usageEvent;
        });
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

    private function normalizeIdempotencyKey(?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $idempotencyKey = trim($idempotencyKey);

        return $idempotencyKey === '' ? null : $idempotencyKey;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function withIdempotencyMetadata(array $metadata, ?string $idempotencyKey): array
    {
        if ($idempotencyKey === null) {
            return $metadata;
        }

        return [
            ...$metadata,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function buildFingerprint(
        Organization $organization,
        string $eventType,
        int $quantity,
        array $metadata
    ): string {
        $serializedPayload = json_encode([
            'organization_id' => $organization->id,
            'event_type' => $eventType,
            'quantity' => $quantity,
            'metadata' => $metadata,
        ]);

        return hash('sha256', $serializedPayload ?: '');
    }

    private function resolveIdempotentUsageEvent(
        Organization $organization,
        string $serviceIdempotencyKey,
        string $metadataIdempotencyKey
    ): ?UsageEvent {
        $storedResponse = $this->idempotencyService->getStoredResponse(
            self::IDEMPOTENCY_SCOPE,
            $serviceIdempotencyKey
        );

        $usageEventId = $storedResponse['usage_event_id'] ?? null;

        if (is_numeric($usageEventId)) {
            $usageEvent = UsageEvent::query()->find((int) $usageEventId);

            if ($usageEvent !== null) {
                return $usageEvent;
            }
        }

        return UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->whereRaw("metadata->>'idempotency_key' = ?", [$metadataIdempotencyKey])
            ->latest('id')
            ->first();
    }

    private function serviceIdempotencyKey(Organization $organization, string $idempotencyKey): string
    {
        return "{$organization->id}:{$idempotencyKey}";
    }
}
