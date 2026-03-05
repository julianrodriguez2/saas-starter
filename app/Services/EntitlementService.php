<?php

namespace App\Services;

use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Organization;

class EntitlementService
{
    public function getLimit(Organization $organization, string $feature): mixed
    {
        $plan = $organization->plan;
        $limits = is_array($plan?->limits) ? $plan->limits : [];

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
}
