<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (
            $user !== null
            && ($user->organizations()->exists() || $user->ownedOrganizations()->exists())
        ) {
            $organizationId = $request->session()->get('organization_id');

            if (is_string($organizationId) && $organizationId !== '') {
                $belongsToOrganization = $user->organizations()
                    ->whereKey($organizationId)
                    ->exists()
                    || $user->ownedOrganizations()
                        ->whereKey($organizationId)
                        ->exists();

                if ($belongsToOrganization) {
                    return redirect()->route('dashboard');
                }

                $request->session()->forget('organization_id');
            }
        }

        return Inertia::render('Organizations/Create', [
            'defaultName' => ($user?->name ?? 'New')."'s Organization",
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $organization = Organization::create([
            'name' => $validated['name'],
            'owner_id' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationUser::ROLE_OWNER,
        ]);

        $request->session()->put('organization_id', $organization->id);

        return redirect()->route('dashboard');
    }
}
