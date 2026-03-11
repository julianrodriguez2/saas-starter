<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendOrganizationRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Admin\AdminOrganizationQueryService;
use App\Services\AuditLogger;
use App\Services\UsageAggregator;
use App\Support\AdminImpersonation;
use App\Support\AuditActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrganizationController extends Controller
{
    public function index(Request $request, AdminOrganizationQueryService $organizationQueryService): Response
    {
        $filters = $organizationQueryService->normalizeFilters($request->only([
            'organization',
            'owner_email',
            'plan',
            'suspended',
        ]));

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizationQueryService->paginateSummaries($filters),
            'filters' => $filters,
            'planOptions' => Plan::query()
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'impersonation' => [
                'active' => AdminImpersonation::isActiveFor($request, $request->user()),
                'organization_id' => AdminImpersonation::impersonatedOrganizationId($request),
            ],
        ]);
    }

    public function show(
        Request $request,
        Organization $organization,
        AdminOrganizationQueryService $organizationQueryService,
        UsageAggregator $usageAggregator
    ): Response {
        return Inertia::render('Admin/Organizations/Show', [
            ...$organizationQueryService->buildDetailPayload($organization, $usageAggregator),
            'isImpersonatingTarget' => AdminImpersonation::isActiveFor($request, $request->user())
                && AdminImpersonation::impersonatedOrganizationId($request) === $organization->id,
        ]);
    }

    public function suspend(
        SuspendOrganizationRequest $request,
        Organization $organization,
        AuditLogger $auditLogger
    ): RedirectResponse
    {
        $validated = $request->validated();

        $suspensionStatus = DB::transaction(function () use (
            $organization,
            $request,
            $validated,
            $auditLogger
        ): string {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadySuspended = (bool) $lockedOrganization->is_suspended;

            $lockedOrganization->is_suspended = true;
            $lockedOrganization->suspended_at = now();
            $lockedOrganization->suspension_reason = $validated['reason'];
            $lockedOrganization->save();

            if (! $alreadySuspended) {
                $auditLogger->logForOrganization(
                    action: AuditActions::ORGANIZATION_SUSPENDED,
                    organization: $lockedOrganization,
                    actor: $request->user(),
                    actorType: 'platform_admin',
                    targetType: 'organization',
                    targetId: $lockedOrganization->id,
                    metadata: [
                        'reason' => $validated['reason'],
                        'source' => 'admin.console',
                    ],
                    request: $request
                );
            }

            return $alreadySuspended ? 'already_suspended' : 'suspended';
        });

        if ($suspensionStatus === 'already_suspended') {
            return redirect()->back()->with('warning', 'Organization suspension was updated.');
        }

        return redirect()->back()->with('success', 'Organization suspended.');
    }

    public function unsuspend(
        Request $request,
        Organization $organization,
        AuditLogger $auditLogger
    ): RedirectResponse
    {
        $wasSuspended = DB::transaction(function () use (
            $organization,
            $request,
            $auditLogger
        ): bool {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasSuspended = (bool) $lockedOrganization->is_suspended;

            if (! $wasSuspended) {
                return false;
            }

            $lockedOrganization->is_suspended = false;
            $lockedOrganization->suspended_at = null;
            $lockedOrganization->suspension_reason = null;
            $lockedOrganization->save();

            $auditLogger->logForOrganization(
                action: AuditActions::ORGANIZATION_UNSUSPENDED,
                organization: $lockedOrganization,
                actor: $request->user(),
                actorType: 'platform_admin',
                targetType: 'organization',
                targetId: $lockedOrganization->id,
                metadata: [
                    'source' => 'admin.console',
                ],
                request: $request
            );

            return true;
        });

        if (! $wasSuspended) {
            return redirect()->back()->with('warning', 'Organization is not suspended.');
        }

        return redirect()->back()->with('success', 'Organization unsuspended.');
    }

    public function impersonate(
        Request $request,
        Organization $organization,
        AuditLogger $auditLogger
    ): RedirectResponse
    {
        $auditLogger->logForOrganization(
            action: AuditActions::ORGANIZATION_IMPERSONATION_STARTED,
            organization: $organization,
            actor: $request->user(),
            actorType: 'platform_admin',
            targetType: 'organization',
            targetId: $organization->id,
            metadata: [
                'impersonating_admin_id' => $request->user()->id,
                'source' => 'admin.console',
            ],
            request: $request
        );

        AdminImpersonation::start($request, $request->user(), $organization);

        return redirect()->route('dashboard')
            ->with('success', "Impersonating organization: {$organization->name}");
    }
}
