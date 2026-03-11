<?php

namespace App\Services;

use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Organization;
use App\Models\Plan;

class EntitlementService
{
    public function __construct(
        private readonly PlatformCacheService $platformCacheService
    ) {
    }

    public function getLimit(Organization $organization, string $feature): mixed
    {
        $limits = $this->resolvePlanLimits($organization);

        return $limits[$feature] ?? null;
    }

    public function hasFeature(Organization $organization, string $feature): bool
    {
        $limit = $this->getLimit($organization, $feature);

        if ($limit === null) {
            return true;
        }

        if (is_bool($limit)) {
            return $limit;
        }

        if (is_numeric($limit)) {
            return (float) $limit > 0;
        }

        return ! empty($limit);
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function checkLimit(Organization $organization, string $feature, int|float $currentValue): void
    {
        $limit = $this->getLimit($organization, $feature);

        if ($limit === null) {
            return;
        }

        if (! is_numeric($limit)) {
            return;
        }

        if ($currentValue > (float) $limit) {
            throw new EntitlementLimitExceededException(
                feature: $feature,
                currentValue: $currentValue,
                limit: (float) $limit
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePlanLimits(Organization $organization): array
    {
        $planId = $this->resolvePlanId($organization);

        if ($planId === null) {
            return [];
        }

        return $this->platformCacheService->rememberPlanLimits(
            $planId,
            static function () use ($planId): array {
                $limits = Plan::query()
                    ->whereKey($planId)
                    ->value('limits');

                return is_array($limits) ? $limits : [];
            }
        );
    }

    private function resolvePlanId(Organization $organization): ?int
    {
        $planId = $organization->plan_id;

        if (is_numeric($planId)) {
            return (int) $planId;
        }

        $resolvedPlanId = Organization::query()
            ->whereKey($organization->id)
            ->value('plan_id');

        return is_numeric($resolvedPlanId) ? (int) $resolvedPlanId : null;
    }
}
