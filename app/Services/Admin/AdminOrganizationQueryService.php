<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\FailedDomainEvent;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\UsageAggregator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminOrganizationQueryService
{
    /**
     * @param array<string, mixed> $rawFilters
     *
     * @return array{organization: string, owner_email: string, plan: string, suspended: string}
     */
    public function normalizeFilters(array $rawFilters): array
    {
        $suspended = strtolower(trim((string) ($rawFilters['suspended'] ?? 'all')));

        if (! in_array($suspended, ['all', 'yes', 'no'], true)) {
            $suspended = 'all';
        }

        return [
            'organization' => trim((string) ($rawFilters['organization'] ?? '')),
            'owner_email' => trim((string) ($rawFilters['owner_email'] ?? '')),
            'plan' => trim((string) ($rawFilters['plan'] ?? '')),
            'suspended' => $suspended,
        ];
    }

    /**
     * @param array{organization: string, owner_email: string, plan: string, suspended: string} $filters
     */
    public function paginateSummaries(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $month = Carbon::now();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $latestSubscriptionStatusSubquery = DB::table('subscriptions')
            ->select('stripe_status')
            ->whereColumn('owner_id', 'organizations.id')
            ->where('owner_type', Organization::class)
            ->latest('created_at')
            ->limit(1);

        $monthlyApiCallSubquery = DB::table('usage_events')
            ->selectRaw('COALESCE(SUM(quantity), 0)')
            ->whereColumn('organization_id', 'organizations.id')
            ->where('event_type', 'api_call')
            ->whereBetween('recorded_at', [$monthStart, $monthEnd]);

        $ownerInMembersSubquery = DB::table('organization_user')
            ->selectRaw('1')
            ->whereColumn('organization_id', 'organizations.id')
            ->whereColumn('user_id', 'organizations.owner_id')
            ->limit(1);

        $query = Organization::query()
            ->select('organizations.*')
            ->with([
                'owner:id,name,email',
                'plan:id,name',
            ])
            ->withCount('users as member_count')
            ->selectSub($latestSubscriptionStatusSubquery, 'subscription_status')
            ->selectSub($monthlyApiCallSubquery, 'monthly_api_calls')
            ->selectSub($ownerInMembersSubquery, 'owner_in_members');

        if ($filters['organization'] !== '') {
            $search = strtolower($filters['organization']);
            $query->whereRaw('lower(organizations.name) like ?', ["%{$search}%"]);
        }

        if ($filters['owner_email'] !== '') {
            $ownerEmail = strtolower($filters['owner_email']);
            $query->whereHas('owner', function (Builder $builder) use ($ownerEmail): void {
                $builder->whereRaw('lower(email) like ?', ["%{$ownerEmail}%"]);
            });
        }

        if ($filters['plan'] !== '') {
            $planName = strtolower($filters['plan']);

            if ($planName === 'free') {
                $query->where(function (Builder $builder): void {
                    $builder->whereNull('organizations.plan_id')
                        ->orWhereHas('plan', function (Builder $planBuilder): void {
                            $planBuilder->whereRaw('lower(name) = ?', ['free']);
                        });
                });
            } else {
                $query->whereHas('plan', function (Builder $builder) use ($planName): void {
                    $builder->whereRaw('lower(name) = ?', [$planName]);
                });
            }
        }

        if ($filters['suspended'] === 'yes') {
            $query->where('organizations.is_suspended', true);
        } elseif ($filters['suspended'] === 'no') {
            $query->where('organizations.is_suspended', false);
        }

        return $query
            ->orderByDesc('organizations.created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Organization $organization): array {
                $memberCount = (int) ($organization->member_count ?? 0);

                if ($organization->owner_id !== null && empty($organization->owner_in_members)) {
                    $memberCount++;
                }

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'owner' => [
                        'name' => $organization->owner?->name ?? 'Unknown',
                        'email' => $organization->owner?->email ?? 'Unknown',
                    ],
                    'plan' => $organization->plan?->name ?? 'Free',
                    'subscription_status' => $this->normalizeSubscriptionStatus(
                        is_string($organization->subscription_status ?? null)
                            ? $organization->subscription_status
                            : null
                    ),
                    'monthly_api_calls' => (int) ($organization->monthly_api_calls ?? 0),
                    'member_count' => $memberCount,
                    'is_suspended' => (bool) $organization->is_suspended,
                    'suspended_at' => $organization->suspended_at?->toIso8601String(),
                    'created_at' => $organization->created_at?->toIso8601String(),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDetailPayload(Organization $organization, UsageAggregator $usageAggregator): array
    {
        $organization->load([
            'owner:id,name,email',
            'plan:id,name,limits',
            'users:id,name,email',
            'auditLogs' => function (Builder $builder): void {
                $builder->latest()
                    ->limit(20)
                    ->with('actor:id,name,email');
            },
        ]);

        $subscription = $organization->subscriptions()
            ->latest('created_at')
            ->first();

        $month = Carbon::now();
        $monthlyUsage = $usageAggregator->getAllMonthlyUsage($organization, $month);
        $monthlyApiCalls = $monthlyUsage['api_call'] ?? 0;

        $members = $organization->users
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $organization->owner_id === $member->id
                    ? OrganizationUser::ROLE_OWNER
                    : ($member->pivot?->role ?? OrganizationUser::ROLE_MEMBER),
            ])
            ->keyBy('id');

        if ($organization->owner !== null && ! $members->has($organization->owner->id)) {
            $members->put($organization->owner->id, [
                'id' => $organization->owner->id,
                'name' => $organization->owner->name,
                'email' => $organization->owner->email,
                'role' => OrganizationUser::ROLE_OWNER,
            ]);
        }

        $failedDomainEvents = FailedDomainEvent::query()
            ->where('payload->organization_id', $organization->id)
            ->latest('failed_at')
            ->limit(15)
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

        return [
            'organizationDetail' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'created_at' => $organization->created_at?->toIso8601String(),
                'owner' => [
                    'id' => $organization->owner?->id,
                    'name' => $organization->owner?->name ?? 'Unknown',
                    'email' => $organization->owner?->email ?? 'Unknown',
                ],
                'plan' => [
                    'id' => $organization->plan?->id,
                    'name' => $organization->plan?->name ?? 'Free',
                    'limits' => $organization->plan?->limits ?? [],
                ],
                'billing' => [
                    'subscription_status' => $this->normalizeSubscriptionStatus(
                        is_string($subscription?->stripe_status) ? $subscription->stripe_status : null
                    ),
                    'subscription_name' => $subscription?->name,
                    'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String()
                        ?? $organization->trial_ends_at?->toIso8601String(),
                    'stripe_customer_id' => $organization->stripe_id ?: $organization->stripe_customer_id,
                    'stripe_subscription_id' => $organization->stripe_subscription_id ?: $subscription?->stripe_id,
                ],
                'suspension' => [
                    'is_suspended' => (bool) $organization->is_suspended,
                    'suspended_at' => $organization->suspended_at?->toIso8601String(),
                    'reason' => $organization->suspension_reason,
                ],
                'monthly_usage' => [
                    'label' => $month->format('F Y'),
                    'totals' => $monthlyUsage,
                    'api_call' => $monthlyApiCalls,
                ],
                'members' => $members
                    ->sortBy('name')
                    ->values()
                    ->all(),
                'recent_usage_events' => UsageEvent::query()
                    ->where('organization_id', $organization->id)
                    ->latest('recorded_at')
                    ->limit(20)
                    ->get()
                    ->map(fn (UsageEvent $usageEvent): array => [
                        'id' => $usageEvent->id,
                        'event_type' => $usageEvent->event_type,
                        'quantity' => $usageEvent->quantity,
                        'recorded_at' => $usageEvent->recorded_at?->toIso8601String(),
                    ])
                    ->all(),
                'recent_audit_logs' => $organization->auditLogs
                    ->map(fn (AuditLog $auditLog): array => [
                        'id' => $auditLog->id,
                        'action' => $auditLog->action,
                        'actor_name' => $auditLog->actor?->name
                            ?? (($auditLog->actor_type ?? 'system') === 'system' ? 'System' : 'Unknown'),
                        'actor_type' => $auditLog->actor_type ?? 'system',
                        'target_type' => $auditLog->target_type,
                        'target_id' => $auditLog->target_id,
                        'created_at' => $auditLog->created_at?->toIso8601String(),
                        'metadata' => $auditLog->metadata ?? [],
                    ])
                    ->all(),
                'recent_failed_domain_events' => $failedDomainEvents,
            ],
        ];
    }

    private function normalizeSubscriptionStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'free';
        }

        return in_array($status, ['trialing', 'active', 'past_due', 'canceled'], true)
            ? $status
            : $status;
    }
}
