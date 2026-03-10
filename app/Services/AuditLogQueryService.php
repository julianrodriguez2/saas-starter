<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogQueryService
{
    /**
     * @param array<string, mixed> $rawFilters
     *
     * @return array{action_query: string, action_exact: string, actor_type: string, from_date: string, to_date: string}
     */
    public function normalizeFilters(array $rawFilters): array
    {
        return [
            'action_query' => trim((string) ($rawFilters['action_query'] ?? '')),
            'action_exact' => trim((string) ($rawFilters['action_exact'] ?? '')),
            'actor_type' => trim((string) ($rawFilters['actor_type'] ?? '')),
            'from_date' => trim((string) ($rawFilters['from_date'] ?? '')),
            'to_date' => trim((string) ($rawFilters['to_date'] ?? '')),
        ];
    }

    /**
     * @param array{action_query: string, action_exact: string, actor_type: string, from_date: string, to_date: string} $filters
     */
    public function paginateForOrganization(
        Organization $organization,
        array $filters,
        int $perPage = 25
    ): LengthAwarePaginator {
        $query = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->with('actor:id,name,email');

        if ($filters['action_query'] !== '') {
            $query->whereRaw('lower(action) like ?', ['%'.strtolower($filters['action_query']).'%']);
        }

        if ($filters['action_exact'] !== '') {
            $query->where('action', $filters['action_exact']);
        }

        if ($filters['actor_type'] !== '') {
            $query->where('actor_type', $filters['actor_type']);
        }

        $fromDate = $this->parseDate($filters['from_date']);

        if ($fromDate !== null) {
            $query->where('created_at', '>=', $fromDate->startOfDay());
        }

        $toDate = $this->parseDate($filters['to_date']);

        if ($toDate !== null) {
            $query->where('created_at', '<=', $toDate->endOfDay());
        }

        return $query
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditLog $auditLog): array => $this->mapRow($auditLog));
    }

    /**
     * @return list<string>
     */
    public function actorTypeOptions(Organization $organization): array
    {
        return AuditLog::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('actor_type')
            ->distinct()
            ->orderBy('actor_type')
            ->pluck('actor_type')
            ->filter(fn (?string $type): bool => is_string($type) && $type !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function actionOptions(Organization $organization): array
    {
        return AuditLog::query()
            ->where('organization_id', $organization->id)
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter(fn (?string $action): bool => is_string($action) && $action !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->id,
            'action' => $auditLog->action,
            'actor' => [
                'id' => $auditLog->actor?->id,
                'name' => $auditLog->actor?->name,
                'email' => $auditLog->actor?->email,
                'type' => $auditLog->actor_type ?? 'system',
            ],
            'target_type' => $auditLog->target_type,
            'target_id' => $auditLog->target_id,
            'metadata' => $auditLog->metadata ?? [],
            'ip_address' => $auditLog->ip_address,
            'user_agent' => $auditLog->user_agent,
            'created_at' => $auditLog->created_at?->toIso8601String(),
        ];
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
