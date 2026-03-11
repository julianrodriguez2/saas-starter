<?php

namespace App\Services;

use App\Models\Organization;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PlatformCacheService
{
    /**
     * @return list<array{id: string, name: string, role: string}>
     */
    public function rememberUserOrganizations(int $userId, Closure $resolver): array
    {
        $organizations = Cache::remember(
            $this->userOrganizationsKey($userId),
            $this->ttl('user_organizations_ttl_seconds', 60),
            static fn () => $resolver()
        );

        if (! is_array($organizations)) {
            return [];
        }

        $normalized = [];

        foreach ($organizations as $organization) {
            if (! is_array($organization)) {
                continue;
            }

            $id = $organization['id'] ?? null;
            $name = $organization['name'] ?? null;
            $role = $organization['role'] ?? null;

            if (! is_string($id) || trim($id) === '') {
                continue;
            }

            if (! is_string($name)) {
                $name = '';
            }

            if (! is_string($role) || trim($role) === '') {
                $role = 'member';
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'role' => $role,
            ];
        }

        return $normalized;
    }

    public function rememberOrganizationSummary(string $organizationId, Closure $resolver): ?array
    {
        $summary = Cache::remember(
            $this->organizationSummaryKey($organizationId),
            $this->ttl('organization_summary_ttl_seconds', 60),
            static fn () => $resolver()
        );

        return is_array($summary) ? $summary : null;
    }

    public function rememberOrganizationAccess(
        int $userId,
        string $organizationId,
        Closure $resolver
    ): bool {
        $access = Cache::remember(
            $this->organizationAccessKey($userId, $organizationId),
            $this->ttl('organization_access_ttl_seconds', 30),
            static fn () => (bool) $resolver()
        );

        return (bool) $access;
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberPlanLimits(int $planId, Closure $resolver): array
    {
        $limits = Cache::remember(
            $this->planLimitsKey($planId),
            $this->ttl('plan_limits_ttl_seconds', 300),
            static fn () => $resolver()
        );

        return is_array($limits) ? $limits : [];
    }

    /**
     * @return array<string, int>
     */
    public function rememberMonthlyUsage(
        string $organizationId,
        string $monthKey,
        Closure $resolver
    ): array {
        $usage = Cache::remember(
            $this->monthlyUsageKey($organizationId, $monthKey),
            $this->ttl('usage_monthly_ttl_seconds', 120),
            static fn () => $resolver()
        );

        if (! is_array($usage)) {
            return [];
        }

        $normalized = [];

        foreach ($usage as $eventType => $total) {
            if (! is_string($eventType) || $eventType === '') {
                continue;
            }

            $normalized[$eventType] = (int) $total;
        }

        return $normalized;
    }

    public function rememberOrganizationMemberCount(
        string $organizationId,
        Closure $resolver
    ): int {
        return (int) Cache::remember(
            $this->organizationMemberCountKey($organizationId),
            $this->ttl('member_count_ttl_seconds', 120),
            static fn () => (int) $resolver()
        );
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function hydrateOrganizationFromSummary(array $summary): Organization
    {
        $organization = new Organization();
        $organization->exists = true;
        $organization->setRawAttributes($summary, true);

        return $organization;
    }

    public function forgetOrganization(string $organizationId): void
    {
        $this->forgetOrganizationSummary($organizationId);
        $this->forgetOrganizationMemberCount($organizationId);
        $this->forgetMonthlyUsage($organizationId, Carbon::now());
    }

    public function forgetOrganizationSummary(string $organizationId): void
    {
        Cache::forget($this->organizationSummaryKey($organizationId));
    }

    public function forgetOrganizationAccess(int $userId, string $organizationId): void
    {
        Cache::forget($this->organizationAccessKey($userId, $organizationId));
    }

    public function forgetPlanLimits(int $planId): void
    {
        Cache::forget($this->planLimitsKey($planId));
    }

    public function forgetUserOrganizations(int $userId): void
    {
        Cache::forget($this->userOrganizationsKey($userId));
    }

    public function forgetMonthlyUsage(string $organizationId, ?Carbon $month = null): void
    {
        $monthKey = ($month ?? now())->format('Y-m');

        Cache::forget($this->monthlyUsageKey($organizationId, $monthKey));
    }

    public function forgetOrganizationMemberCount(string $organizationId): void
    {
        Cache::forget($this->organizationMemberCountKey($organizationId));
    }

    private function organizationSummaryKey(string $organizationId): string
    {
        return "platform:org:{$organizationId}:summary";
    }

    private function organizationAccessKey(int $userId, string $organizationId): string
    {
        return "platform:user:{$userId}:org:{$organizationId}:access";
    }

    private function planLimitsKey(int $planId): string
    {
        return "platform:plan:{$planId}:limits";
    }

    private function userOrganizationsKey(int $userId): string
    {
        return "platform:user:{$userId}:organizations";
    }

    private function monthlyUsageKey(string $organizationId, string $monthKey): string
    {
        return "platform:org:{$organizationId}:usage:{$monthKey}";
    }

    private function organizationMemberCountKey(string $organizationId): string
    {
        return "platform:org:{$organizationId}:member_count";
    }

    private function ttl(string $configKey, int $default): int
    {
        return max((int) config("platform.cache.{$configKey}", $default), 1);
    }
}
