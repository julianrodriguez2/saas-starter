<?php

namespace App\Http\Controllers;

use App\Support\CurrentOrganization;
use Inertia\Inertia;
use Inertia\Response;

class BillingPlanController extends Controller
{
    public function __invoke(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->organization?->load('plan');

        abort_if($organization === null, 404);

        $limits = is_array($organization->plan?->limits)
            ? $organization->plan->limits
            : [];

        return Inertia::render('Billing/Plan', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'plan' => [
                'name' => $organization->plan?->name ?? 'Unassigned',
                'limits' => [
                    'team_members' => $limits['team_members'] ?? null,
                    'projects' => $limits['projects'] ?? null,
                    'api_calls_per_month' => $limits['api_calls_per_month'] ?? null,
                    'advanced_features' => $limits['advanced_features'] ?? false,
                ],
            ],
        ]);
    }
}
