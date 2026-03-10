<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\AuditLogQueryService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuditLogQueryService $auditLogQueryService
    ): Response {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('update', $organization);

        $filters = $auditLogQueryService->normalizeFilters($request->query());

        return Inertia::render('AuditLogs/Index', [
            'auditOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'filters' => $filters,
            'auditLogs' => $auditLogQueryService->paginateForOrganization($organization, $filters),
            'actorTypeOptions' => $auditLogQueryService->actorTypeOptions($organization),
            'actionOptions' => $auditLogQueryService->actionOptions($organization),
        ]);
    }

    public function show(
        CurrentOrganization $currentOrganization,
        AuditLog $auditLog,
        AuditLogQueryService $auditLogQueryService
    ): Response {
        $organization = $this->resolveOrganization($currentOrganization);

        Gate::authorize('update', $organization);
        abort_if($auditLog->organization_id !== $organization->id, 404);

        $auditLog->load('actor:id,name,email');

        return Inertia::render('AuditLogs/Show', [
            'auditOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'auditLog' => $auditLogQueryService->mapRow($auditLog),
        ]);
    }

    private function resolveOrganization(CurrentOrganization $currentOrganization): Organization
    {
        $organization = $currentOrganization->organization;

        abort_if($organization === null, 404);

        return $organization;
    }
}
