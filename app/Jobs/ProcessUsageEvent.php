<?php

namespace App\Jobs;

use App\Models\UsageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUsageEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $usageEventId
    ) {
    }

    public function handle(): void
    {
        $usageEvent = UsageEvent::query()->find($this->usageEventId);

        if ($usageEvent === null) {
            return;
        }

        // Placeholder for future async fan-out (Stripe metered sync, analytics, anomaly checks).
    }
}
