<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IdempotencyService
{
    /**
     * @throws InvalidArgumentException
     * @throws IdempotencyConflictException
     */
    public function acquire(string $scope, string $key, ?string $fingerprint = null): IdempotencyKey
    {
        [$scope, $key] = $this->normalizeScopeAndKey($scope, $key);
        $fingerprint = $this->normalizeFingerprint($fingerprint);

        try {
            $idempotencyKey = IdempotencyKey::query()->firstOrCreate(
                ['scope' => $scope, 'key' => $key],
                ['fingerprint' => $fingerprint]
            );
        } catch (QueryException) {
            $idempotencyKey = IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('key', $key)
                ->firstOrFail();
        }

        if ($fingerprint !== null) {
            if ($idempotencyKey->fingerprint === null) {
                $idempotencyKey->fingerprint = $fingerprint;
                $idempotencyKey->save();
            } elseif ($idempotencyKey->fingerprint !== $fingerprint) {
                throw new IdempotencyConflictException($scope, $key);
            }
        }

        return $idempotencyKey;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function alreadyProcessed(string $scope, string $key): bool
    {
        [$scope, $key] = $this->normalizeScopeAndKey($scope, $key);

        return IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->whereNotNull('processed_at')
            ->exists();
    }

    /**
     * @param array<string, mixed> $responsePayload
     *
     * @throws InvalidArgumentException
     */
    public function markProcessed(string $scope, string $key, array $responsePayload = []): void
    {
        [$scope, $key] = $this->normalizeScopeAndKey($scope, $key);

        DB::transaction(function () use ($scope, $key, $responsePayload): void {
            $idempotencyKey = IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($idempotencyKey === null) {
                $idempotencyKey = IdempotencyKey::query()->create([
                    'scope' => $scope,
                    'key' => $key,
                ]);
            }

            if ($idempotencyKey->processed_at !== null) {
                return;
            }

            $idempotencyKey->processed_at = now();
            $idempotencyKey->response_payload = $responsePayload;
            $idempotencyKey->save();
        });
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    public function getStoredResponse(string $scope, string $key): ?array
    {
        [$scope, $key] = $this->normalizeScopeAndKey($scope, $key);

        $idempotencyKey = IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if ($idempotencyKey === null || $idempotencyKey->processed_at === null) {
            return null;
        }

        return is_array($idempotencyKey->response_payload)
            ? $idempotencyKey->response_payload
            : null;
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException
     */
    private function normalizeScopeAndKey(string $scope, string $key): array
    {
        $scope = trim($scope);
        $key = trim($key);

        if ($scope === '') {
            throw new InvalidArgumentException('Idempotency scope must be a non-empty string.');
        }

        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key must be a non-empty string.');
        }

        return [$scope, $key];
    }

    private function normalizeFingerprint(?string $fingerprint): ?string
    {
        if ($fingerprint === null) {
            return null;
        }

        $fingerprint = trim($fingerprint);

        return $fingerprint === '' ? null : $fingerprint;
    }
}
