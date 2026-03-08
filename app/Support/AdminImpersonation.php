<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class AdminImpersonation
{
    public const IMPERSONATING_ADMIN_ID = 'impersonating_admin_id';
    public const IMPERSONATED_ORGANIZATION_ID = 'impersonated_organization_id';
    public const ORIGINAL_ORGANIZATION_ID = 'impersonation_original_organization_id';
    public const STARTED_AT = 'impersonation_started_at';

    public static function isActiveFor(Request $request, ?User $user): bool
    {
        if ($user === null || ! $user->isSuperAdmin()) {
            return false;
        }

        $impersonatingAdminId = $request->session()->get(self::IMPERSONATING_ADMIN_ID);

        return is_numeric($impersonatingAdminId) && (int) $impersonatingAdminId === (int) $user->id;
    }

    public static function impersonatedOrganizationId(Request $request): ?string
    {
        $organizationId = $request->session()->get(self::IMPERSONATED_ORGANIZATION_ID);

        return is_string($organizationId) && $organizationId !== '' ? $organizationId : null;
    }

    public static function start(Request $request, User $admin, Organization $organization): void
    {
        $session = $request->session();

        if (! self::isActiveFor($request, $admin)) {
            $currentOrganizationId = $session->get('organization_id');

            if (is_string($currentOrganizationId) && $currentOrganizationId !== '') {
                $session->put(self::ORIGINAL_ORGANIZATION_ID, $currentOrganizationId);
            } else {
                $session->forget(self::ORIGINAL_ORGANIZATION_ID);
            }
        }

        $session->put(self::IMPERSONATING_ADMIN_ID, $admin->id);
        $session->put(self::IMPERSONATED_ORGANIZATION_ID, $organization->id);
        $session->put(self::STARTED_AT, now()->toIso8601String());
        $session->put('organization_id', $organization->id);
    }

    /**
     * @return array{impersonated_organization_id: string|null, original_organization_id: string|null}
     */
    public static function stop(Request $request): array
    {
        $session = $request->session();

        $impersonatedOrganizationId = self::impersonatedOrganizationId($request);
        $originalOrganizationId = $session->get(self::ORIGINAL_ORGANIZATION_ID);
        $originalOrganizationId = is_string($originalOrganizationId) && $originalOrganizationId !== ''
            ? $originalOrganizationId
            : null;

        $session->forget([
            self::IMPERSONATING_ADMIN_ID,
            self::IMPERSONATED_ORGANIZATION_ID,
            self::ORIGINAL_ORGANIZATION_ID,
            self::STARTED_AT,
        ]);

        if ($originalOrganizationId !== null) {
            $session->put('organization_id', $originalOrganizationId);
        } else {
            $session->forget('organization_id');
        }

        return [
            'impersonated_organization_id' => $impersonatedOrganizationId,
            'original_organization_id' => $originalOrganizationId,
        ];
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget([
            self::IMPERSONATING_ADMIN_ID,
            self::IMPERSONATED_ORGANIZATION_ID,
            self::ORIGINAL_ORGANIZATION_ID,
            self::STARTED_AT,
        ]);
    }
}
