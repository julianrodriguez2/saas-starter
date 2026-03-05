<?php

namespace App\Exceptions;

use RuntimeException;

class EntitlementLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        public readonly int|float $currentValue,
        public readonly int|float $limit
    ) {
        parent::__construct(
            "Limit exceeded for feature [{$feature}]. Current value: {$currentValue}, limit: {$limit}."
        );
    }
}
