<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Admin\AdminOrganizationQueryService;
use App\Services\UsageAggregator;
use App\Support\AdminImpersonation;
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

    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $suspensionStatus = DB::transaction(function () use ($organization, $request, $validated): string {
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
                $lockedOrganization->auditLogs()->create([
                    'actor_id' => $request->user()->id,
                    'action' => 'admin.organization.suspended',
                    'metadata' => [
                        'reason' => $validated['reason'],
                    ],
                ]);
            }

            return $alreadySuspended ? 'already_suspended' : 'suspended';
        });

        if ($suspensionStatus === 'already_suspended') {
            return redirect()->back()->with('warning', 'Organization suspension was updated.');
        }

        return redirect()->back()->with('success', 'Organization suspended.');
    }

    public function unsuspend(Request $request, Organization $organization): RedirectResponse
    {
        $wasSuspended = DB::transaction(function () use ($organization, $request): bool {
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

            $lockedOrganization->auditLogs()->create([
                'actor_id' => $request->user()->id,
                'action' => 'admin.organization.unsuspended',
                'metadata' => [
                    'source' => 'admin.console',
                ],
            ]);

            return true;
        });

        if (! $wasSuspended) {
            return redirect()->back()->with('warning', 'Organization is not suspended.');
        }

        return redirect()->back()->with('success', 'Organization unsuspended.');
    }

    public function impersonate(Request $request, Organization $organization): RedirectResponse
    {
        DB::transaction(function () use ($request, $organization): void {
            $organization->auditLogs()->create([
                'actor_id' => $request->user()->id,
                'action' => 'admin.impersonation.started',
                'metadata' => [
                    'impersonating_admin_id' => $request->user()->id,
                    'source' => 'admin.console',
                ],
            ]);
        });

        AdminImpersonation::start($request, $request->user(), $organization);

        return redirect()->route('dashboard')
            ->with('success', "Impersonating organization: {$organization->name}");
    }
}
