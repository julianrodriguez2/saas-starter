<?php

namespace App\Services;

use App\Models\FailedDomainEvent;
use Throwable;

class DomainEventFailureService
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function recordFailure(
        string $source,
        ?string $eventKey = null,
        ?string $eventType = null,
        ?array $payload = null,
        Throwable|string|null $error = null
    ): FailedDomainEvent {
        $errorMessage = $error instanceof Throwable
            ? sprintf('%s: %s', $error::class, $error->getMessage())
            : (string) ($error ?? 'Unknown domain processing failure.');

        return FailedDomainEvent::query()->create([
            'source' => trim($source) !== '' ? trim($source) : 'unknown',
            'event_key' => $eventKey,
            'event_type' => $eventType,
            'payload' => $payload,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    public function resolve(FailedDomainEvent $failedDomainEvent): FailedDomainEvent
    {
        if ($failedDomainEvent->resolved_at === null) {
            $failedDomainEvent->resolved_at = now();
            $failedDomainEvent->save();
        }

        return $failedDomainEvent;
    }
}
