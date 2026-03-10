<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use RuntimeException;

class ApiKeyService
{
    /**
     * @return array{apiKey: ApiKey, plainTextKey: string}
     */
    public function createKey(Organization $organization, string $name, ?int $createdByUserId = null): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('API key name cannot be empty.');
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            [$plainTextKey, $keyPrefix, $keyHash] = $this->generateMaterial();

            try {
                $apiKey = ApiKey::query()->create([
                    'organization_id' => $organization->id,
                    'name' => $name,
                    'key_prefix' => $keyPrefix,
                    'key_hash' => $keyHash,
                    'created_by_user_id' => $createdByUserId,
                ]);
            } catch (QueryException) {
                continue;
            }

            if ($apiKey !== null) {
                return [
                    'apiKey' => $apiKey,
                    'plainTextKey' => $plainTextKey,
                ];
            }
        }

        throw new RuntimeException('Unable to generate a unique API key.');
    }

    public function revoke(ApiKey $apiKey): bool
    {
        if ($apiKey->revoked_at !== null) {
            return false;
        }

        $apiKey->revoked_at = now();
        $apiKey->save();

        return true;
    }

    public function findActiveByToken(string $plainTextToken): ?ApiKey
    {
        $plainTextToken = trim($plainTextToken);

        if ($plainTextToken === '') {
            return null;
        }

        $keyHash = hash('sha256', $plainTextToken);

        return ApiKey::query()
            ->with('organization')
            ->where('key_hash', $keyHash)
            ->whereNull('revoked_at')
            ->first();
    }

    public function touchLastUsed(ApiKey $apiKey): void
    {
        $now = now();
        $lastUsedAt = $apiKey->last_used_at;

        if ($lastUsedAt instanceof Carbon && $lastUsedAt->greaterThan($now->copy()->subMinute())) {
            return;
        }

        $apiKey->forceFill([
            'last_used_at' => $now,
        ])->save();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function generateMaterial(): array
    {
        $identifier = bin2hex(random_bytes(4));
        $secret = bin2hex(random_bytes(32));
        $plainTextKey = "sk_{$identifier}_{$secret}";
        $keyPrefix = "sk_{$identifier}";
        $keyHash = hash('sha256', $plainTextKey);

        return [$plainTextKey, $keyPrefix, $keyHash];
    }
}
