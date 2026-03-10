<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use App\Support\AuditActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->user() !== null) {
            $auditLogger->logPlatformEvent(
                action: AuditActions::AUTH_LOGIN,
                actor: $request->user(),
                targetType: 'user',
                targetId: (string) $request->user()->id,
                request: $request
            );
        }

        $organizationId = $request->user()
            ?->organizations()
            ->orderBy('organizations.created_at')
            ->value('organizations.id');

        if ($organizationId !== null) {
            $request->session()->put('organization_id', $organizationId);
        } else {
            $request->session()->forget('organization_id');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
