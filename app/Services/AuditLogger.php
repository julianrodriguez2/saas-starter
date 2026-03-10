<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuditLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        ?Organization $organization = null,
        mixed $actor = null,
        ?string $actorType = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        $resolvedAction = trim($action);
        $resolvedActorType = $this->resolveActorType($actor, $actorType);
        $resolvedActorId = $this->resolveActorId($actor);
        $safeMetadata = $this->sanitizeMetadata($metadata);

        return AuditLog::query()->create([
            'organization_id' => $organization?->id,
            'actor_id' => $resolvedActorId,
            'actor_type' => $resolvedActorType,
            'action' => $resolvedAction !== '' ? $resolvedAction : 'system.event',
            'target_type' => $this->normalizeString($targetType),
            'target_id' => $this->normalizeString($targetId),
            'metadata' => $safeMetadata === [] ? null : $safeMetadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $this->normalizeString($request?->userAgent()),
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logForOrganization(
        string $action,
        Organization $organization,
        mixed $actor = null,
        ?string $actorType = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        return $this->log(
            action: $action,
            organization: $organization,
            actor: $actor,
            actorType: $actorType,
            targetType: $targetType,
            targetId: $targetId,
            metadata: $metadata,
            request: $request
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logPlatformEvent(
        string $action,
        mixed $actor = null,
        ?string $actorType = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?Request $request = null
    ): AuditLog {
        return $this->log(
            action: $action,
            organization: null,
            actor: $actor,
            actorType: $actorType,
            targetType: $targetType,
            targetId: $targetId,
            metadata: $metadata,
            request: $request
        );
    }

    private function resolveActorId(mixed $actor): ?int
    {
        return $actor instanceof User ? $actor->id : null;
    }

    private function resolveActorType(mixed $actor, ?string $actorType): ?string
    {
        $actorType = $this->normalizeString($actorType);

        if ($actorType !== null) {
            return $actorType;
        }

        return match (true) {
            $actor instanceof User => 'user',
            $actor instanceof ApiKey => 'api_key',
            default => 'system',
        };
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }

        $normalized = $this->normalizeValue($metadata, '');

        if (! is_array($normalized)) {
            return [];
        }

        $encoded = json_encode($normalized, JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (! is_string($encoded)) {
            return [];
        }

        if (strlen($encoded) > 5000) {
            return [
                '_truncated' => true,
                '_excerpt' => substr($encoded, 0, 5000),
            ];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeValue(mixed $value, string $key): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $nestedKey => $nestedValue) {
                $nestedKeyString = is_string($nestedKey) ? $nestedKey : (string) $nestedKey;
                $normalized[$nestedKeyString] = $this->normalizeValue($nestedValue, $nestedKeyString);
            }

            return $normalized;
        }

        if ($value instanceof User) {
            return Arr::only($value->toArray(), ['id', 'name', 'email']);
        }

        if ($value instanceof Organization) {
            return Arr::only($value->toArray(), ['id', 'name']);
        }

        if ($value instanceof ApiKey) {
            return Arr::only($value->toArray(), ['id', 'name', 'key_prefix']);
        }

        if (method_exists($value, 'toArray')) {
            $arrayValue = $value->toArray();

            return is_array($arrayValue)
                ? $this->normalizeValue($arrayValue, $key)
                : class_basename($value);
        }

        return class_basename($value);
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $key = strtolower($key);
        $sensitiveFragments = [
            'password',
            'secret',
            'token',
            'authorization',
            'api_key_plaintext',
            'card',
            'cvv',
        ];

        foreach ($sensitiveFragments as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeString(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
