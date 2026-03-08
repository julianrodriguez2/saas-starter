<?php

namespace App\Services\Admin;

use App\Models\FailedDomainEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminMetricsService
{
    /**
     * @return array<string, int>
     */
    public function getSummaryMetrics(): array
    {
        $ownerType = Organization::class;
        $activePaidStatuses = ['trialing', 'active', 'past_due'];

        $activePaidOrganizations = (int) DB::table('subscriptions')
            ->where('owner_type', $ownerType)
            ->whereIn('stripe_status', $activePaidStatuses)
            ->distinct('owner_id')
            ->count('owner_id');

        return [
            'total_organizations' => Organization::query()->count(),
            'active_paid_organizations' => $activePaidOrganizations,
            'free_organizations' => Organization::query()
                ->where(function ($query): void {
                    $query->whereNull('plan_id')
                        ->orWhereHas('plan', function ($planQuery): void {
                            $planQuery->whereRaw('lower(name) = ?', ['free']);
                        });
                })
                ->count(),
            'suspended_organizations' => Organization::query()
                ->where('is_suspended', true)
                ->count(),
            'total_users' => User::query()->count(),
            'unresolved_failed_domain_events' => FailedDomainEvent::query()
                ->whereNull('resolved_at')
                ->count(),
        ];
    }
}
