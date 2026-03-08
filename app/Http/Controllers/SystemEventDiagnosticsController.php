<?php

namespace App\Http\Controllers;

use App\Models\FailedDomainEvent;
use App\Services\DomainEventFailureService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemEventDiagnosticsController extends Controller
{
    public function index(): Response
    {
        $failedEvents = FailedDomainEvent::query()
            ->latest('failed_at')
            ->limit(100)
            ->get()
            ->map(fn (FailedDomainEvent $failedEvent): array => [
                'id' => $failedEvent->id,
                'source' => $failedEvent->source,
                'event_key' => $failedEvent->event_key,
                'event_type' => $failedEvent->event_type,
                'error_message' => $failedEvent->error_message,
                'failed_at' => $failedEvent->failed_at?->toIso8601String(),
                'resolved_at' => $failedEvent->resolved_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('System/Events', [
            'failedEvents' => $failedEvents,
        ]);
    }

    public function resolve(
        FailedDomainEvent $failedEvent,
        DomainEventFailureService $domainEventFailureService
    ): RedirectResponse {
        $domainEventFailureService->resolve($failedEvent);

        return redirect()->route('system.events.index')
            ->with('success', 'Event marked as resolved.');
    }
}
