<?php

namespace App\Http\Controllers;

use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Services\EntitlementService;
use App\Services\UsageAggregator;
use App\Services\UsageRecorder;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class UsageController extends Controller
{
    public function index(
        Request $request,
        CurrentOrganization $currentOrganization,
        UsageAggregator $usageAggregator,
        EntitlementService $entitlementService
    ): Response {
        $organization = $this->resolveOrganization($currentOrganization)->load('plan');
        $month = Carbon::now();
        $monthlyUsage = $usageAggregator->getAllMonthlyUsage($organization, $month);
        $monthlyUsage['api_call'] = $monthlyUsage['api_call'] ?? 0;

        $apiCallUsage = $monthlyUsage['api_call'] ?? 0;
        $apiCallLimit = $entitlementService->getLimit($organization, 'api_calls_per_month');
        $apiUnlimited = $apiCallLimit === null;
        $apiPercentUsed = (
            ! $apiUnlimited
            && is_numeric($apiCallLimit)
            && (float) $apiCallLimit > 0
        )
            ? round(($apiCallUsage / (float) $apiCallLimit) * 100, 2)
            : null;

        $currentRole = $request->user()->roleInOrganization($organization);

        return Inertia::render('Usage/Index', [
            'usageOrganization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'currentPlan' => [
                'id' => $organization->plan?->id,
                'name' => $organization->plan?->name ?? 'Free',
                'limits' => $organization->plan?->limits ?? [],
            ],
            'monthLabel' => $month->format('F Y'),
            'usageTotals' => $monthlyUsage,
            'apiCallSummary' => [
                'used' => $apiCallUsage,
                'limit' => is_numeric($apiCallLimit) ? (int) $apiCallLimit : null,
                'unlimited' => $apiUnlimited,
                'percent_used' => $apiPercentUsed,
            ],
            'canRecordTestUsage' => in_array(
                $currentRole,
                [OrganizationUser::ROLE_OWNER, OrganizationUser::ROLE_ADMIN],
                true
            ),
        ]);
    }

    public function testRecord(
        Request $request,
        CurrentOrganization $currentOrganization,
        UsageRecorder $usageRecorder
    ): RedirectResponse {
        $organization = $this->resolveOrganization($currentOrganization);

        try {
            $usageRecorder->record(
                organization: $organization,
                eventType: 'api_call',
                quantity: 1,
                metadata: [
                    'source' => 'usage.test-record',
                    'actor_id' => $request->user()->id,
                ]
            );
        } catch (EntitlementLimitExceededException) {
            return redirect()->route('usage.index')
                ->withErrors([
                    'usage' => 'API call monthly limit reached for your current plan.',
                ])
                ->with('error', 'API call monthly limit reached for your current plan.');
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('usage.index')
                ->withErrors([
                    'usage' => $exception->getMessage(),
                ])
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('usage.index')
            ->with('success', 'Recorded 1 API call usage event.');
    }

    private function resolveOrganization(CurrentOrganization $currentOrganization): Organization
    {
        $organization = $currentOrganization->organization;

        abort_if($organization === null, 404);

        return $organization;
    }
}
