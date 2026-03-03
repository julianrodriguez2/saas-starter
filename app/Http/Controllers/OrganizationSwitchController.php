<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'uuid'],
        ]);

        $organizationId = $validated['organization_id'];

        $user = $request->user();

        $belongsToOrganization = $user->organizations()
            ->whereKey($organizationId)
            ->exists()
            || $user->ownedOrganizations()
                ->whereKey($organizationId)
                ->exists();

        abort_if(! $belongsToOrganization, 403);

        $request->session()->put('organization_id', $organizationId);

        return redirect()->back();
    }
}
