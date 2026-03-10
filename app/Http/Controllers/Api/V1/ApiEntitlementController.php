<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\EntitlementLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckEntitlementRequest;
use App\Services\EntitlementService;
use App\Support\CurrentApiOrganization;
use Illuminate\Http\JsonResponse;

class ApiEntitlementController extends Controller
{
    public function check(
        CheckEntitlementRequest $request,
        CurrentApiOrganization $currentApiOrganization,
        EntitlementService $entitlementService
    ): JsonResponse {
        $organization = $currentApiOrganization->organization;
        $validated = $request->validated();
        $feature = $validated['feature'];
        $currentValue = array_key_exists('current_value', $validated)
            ? (int) $validated['current_value']
            : null;

        $limit = $entitlementService->getLimit($organization, $feature);
        $allowed = $entitlementService->hasFeature($organization, $feature);

        if ($currentValue !== null && is_numeric($limit)) {
            try {
                $entitlementService->checkLimit($organization, $feature, $currentValue);
                $allowed = true;
            } catch (EntitlementLimitExceededException) {
                $allowed = false;
            }
        }

        return response()->json([
            'success' => true,
            'feature' => $feature,
            'allowed' => $allowed,
            'limit' => $limit,
            'unlimited' => $limit === null,
            'current_value' => $currentValue,
        ]);
    }
}
