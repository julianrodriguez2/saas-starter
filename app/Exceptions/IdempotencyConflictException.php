<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $scope,
        public readonly string $key
    ) {
        parent::__construct(
            "Idempotency conflict detected for scope [{$scope}] and key [{$key}]."
        );
    }
}
