<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuditActions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        [$user, $organization] = DB::transaction(function () use ($request): array {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $organization = Organization::create([
                'name' => "{$user->name}'s Organization",
                'owner_id' => $user->id,
            ]);

            $organization->users()->attach($user->id, [
                'role' => OrganizationUser::ROLE_OWNER,
            ]);

            return [$user, $organization];
        });

        event(new Registered($user));

        $auditLogger->logPlatformEvent(
            action: AuditActions::AUTH_REGISTERED,
            actor: $user,
            targetType: 'user',
            targetId: (string) $user->id,
            request: $request
        );

        $auditLogger->logForOrganization(
            action: AuditActions::ORGANIZATION_CREATED,
            organization: $organization,
            actor: $user,
            targetType: 'organization',
            targetId: $organization->id,
            metadata: [
                'source' => 'auth.registration',
            ],
            request: $request
        );

        Auth::login($user);
        $request->session()->put('organization_id', $organization->id);

        return redirect(route('dashboard', absolute: false));
    }
}
