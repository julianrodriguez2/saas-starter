<?php

namespace App\Jobs;

use App\Models\UsageEvent;
use App\Services\DomainEventFailureService;
use App\Services\IdempotencyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessUsageEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const IDEMPOTENCY_SCOPE = 'usage.job';

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly int $usageEventId
    ) {
        $this->tries = max((int) config('platform.queue.process_usage_event.tries', 3), 1);
        $this->timeout = max((int) config('platform.queue.process_usage_event.timeout_seconds', 60), 1);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $configuredBackoff = config('platform.queue.process_usage_event.backoff_seconds', [30, 60, 120]);

        if (! is_array($configuredBackoff) || $configuredBackoff === []) {
            return [30, 60, 120];
        }

        return array_values(array_map(
            static fn (mixed $seconds): int => max((int) $seconds, 1),
            $configuredBackoff
        ));
    }

    public function handle(
        IdempotencyService $idempotencyService,
        DomainEventFailureService $domainEventFailureService
    ): void {
        $usageEvent = UsageEvent::query()->find($this->usageEventId);

        if ($usageEvent === null) {
            return;
        }

        $idempotencyKey = (string) $usageEvent->id;
        $fingerprintPayload = json_encode([
            'usage_event_id' => $usageEvent->id,
            'event_type' => $usageEvent->event_type,
            'quantity' => $usageEvent->quantity,
            'recorded_at' => $usageEvent->recorded_at?->toIso8601String(),
        ]);
        $fingerprint = hash('sha256', $fingerprintPayload ?: '');

        try {
            $idempotencyService->acquire(
                self::IDEMPOTENCY_SCOPE,
                $idempotencyKey,
                $fingerprint
            );

            if ($idempotencyService->alreadyProcessed(self::IDEMPOTENCY_SCOPE, $idempotencyKey)) {
                return;
            }

            // Placeholder: future async processing hooks live here.

            $idempotencyService->markProcessed(
                self::IDEMPOTENCY_SCOPE,
                $idempotencyKey,
                [
                    'usage_event_id' => $usageEvent->id,
                    'event_type' => $usageEvent->event_type,
                ]
            );
        } catch (Throwable $exception) {
            if ($this->attempts() >= $this->tries) {
                $domainEventFailureService->recordFailure(
                    source: 'usage_job',
                    eventKey: $idempotencyKey,
                    eventType: $usageEvent->event_type,
                    payload: [
                        'usage_event_id' => $usageEvent->id,
                        'organization_id' => $usageEvent->organization_id,
                        'quantity' => $usageEvent->quantity,
                        'attempt' => $this->attempts(),
                        'tries' => $this->tries,
                    ],
                    error: $exception
                );
            }

            throw $exception;
        }
    }
}
