<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\UsageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UsageAggregator
{
    public function getMonthlyUsage(
        Organization $organization,
        string $eventType,
        ?Carbon $month = null
    ): int {
        $eventType = Str::lower(trim($eventType));

        if ($eventType === '') {
            return 0;
        }

        [$start, $end] = $this->monthBounds($month);

        return (int) UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->where('event_type', $eventType)
            ->whereBetween('recorded_at', [$start, $end])
            ->sum('quantity');
    }

    /**
     * @return array<string, int>
     */
    public function getAllMonthlyUsage(Organization $organization, ?Carbon $month = null): array
    {
        [$start, $end] = $this->monthBounds($month);

        return UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('recorded_at', [$start, $end])
            ->selectRaw('event_type, SUM(quantity) as total_quantity')
            ->groupBy('event_type')
            ->pluck('total_quantity', 'event_type')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthBounds(?Carbon $month): array
    {
        $referenceMonth = ($month ?? now())->copy();

        return [
            $referenceMonth->copy()->startOfMonth(),
            $referenceMonth->copy()->endOfMonth(),
        ];
    }
}
