<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Support\AdminImpersonation;
use App\Support\AuditActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminImpersonationController extends Controller
{
    public function stop(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        if (! AdminImpersonation::isActiveFor($request, $request->user())) {
            return redirect()->route('admin.organizations.index')
                ->with('warning', 'No active impersonation session.');
        }

        $context = AdminImpersonation::stop($request);
        $impersonatedOrganizationId = $context['impersonated_organization_id'];
        $originalOrganizationId = $context['original_organization_id'];

        if ($originalOrganizationId !== null && ! Organization::query()->whereKey($originalOrganizationId)->exists()) {
            $request->session()->forget('organization_id');
        }

        if ($impersonatedOrganizationId !== null) {
            $organization = Organization::query()->find($impersonatedOrganizationId);

            if ($organization !== null) {
                $auditLogger->logForOrganization(
                    action: AuditActions::ORGANIZATION_IMPERSONATION_STOPPED,
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
            }
        }

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Impersonation stopped.');
    }
}
