<?php

use App\Http\Controllers\Api\V1\UsageEventApiController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminImpersonationController;
use App\Http\Controllers\Admin\AdminOrganizationController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DeveloperApiDocsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemEventDiagnosticsController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UsageController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->middleware(VerifyWebhookSignature::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('stripe.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/organizations/create', [OrganizationController::class, 'create'])
        ->name('organizations.create');

    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->name('organizations.store');

    Route::post('/organizations/switch', OrganizationSwitchController::class)
        ->name('organizations.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'resolve.organization'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/organizations/settings', OrganizationSettingsController::class)
        ->name('organizations.settings');

    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing.index');

    Route::redirect('/billing/plan', '/billing')
        ->name('billing.plan');

    Route::get('/billing/checkout/success', [BillingController::class, 'checkoutSuccess'])
        ->name('billing.checkout.success');

    Route::get('/billing/checkout/cancel', [BillingController::class, 'checkoutCancel'])
        ->name('billing.checkout.cancel');

    Route::get('/usage', [UsageController::class, 'index'])
        ->name('usage.index');

    Route::get('/developers/api', DeveloperApiDocsController::class)
        ->name('developers.api');
});

Route::middleware(['auth', 'resolve.organization', 'org.role:admin'])->group(function () {
    Route::get('/organizations/members', [OrganizationMemberController::class, 'index'])
        ->name('organizations.members.index');

    Route::post('/organizations/members/invite', [OrganizationMemberController::class, 'invite'])
        ->name('organizations.members.invite');

    Route::delete('/organizations/members/{user}', [OrganizationMemberController::class, 'destroy'])
        ->name('organizations.members.destroy');

    Route::patch('/organizations/members/{user}/role', [OrganizationMemberController::class, 'updateRole'])
        ->name('organizations.members.update-role');

    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])
        ->name('billing.checkout');

    Route::post('/billing/portal', [BillingController::class, 'portal'])
        ->name('billing.portal');

    Route::post('/usage/test-record', [UsageController::class, 'testRecord'])
        ->name('usage.test-record');

    Route::get('/settings/api-keys', [ApiKeyController::class, 'index'])
        ->name('settings.api-keys.index');

    Route::post('/settings/api-keys', [ApiKeyController::class, 'store'])
        ->name('settings.api-keys.store');

    Route::post('/settings/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke'])
        ->name('settings.api-keys.revoke');
});

Route::middleware(['auth', 'super.admin'])->group(function () {
    Route::get('/system/events', [SystemEventDiagnosticsController::class, 'index'])
        ->name('system.events.index');

    Route::post('/system/events/{failedEvent}/resolve', [SystemEventDiagnosticsController::class, 'resolve'])
        ->name('system.events.resolve');
});

Route::middleware(['auth', 'super.admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)
            ->name('dashboard');

        Route::get('/organizations', [AdminOrganizationController::class, 'index'])
            ->name('organizations.index');

        Route::get('/organizations/{organization}', [AdminOrganizationController::class, 'show'])
            ->name('organizations.show');

        Route::post('/organizations/{organization}/suspend', [AdminOrganizationController::class, 'suspend'])
            ->name('organizations.suspend');

        Route::post('/organizations/{organization}/unsuspend', [AdminOrganizationController::class, 'unsuspend'])
            ->name('organizations.unsuspend');

        Route::post('/organizations/{organization}/impersonate', [AdminOrganizationController::class, 'impersonate'])
            ->name('organizations.impersonate');

        Route::post('/impersonation/stop', [AdminImpersonationController::class, 'stop'])
            ->name('impersonation.stop');
    });

Route::middleware(['auth', 'resolve.organization'])
    ->prefix('api/v1')
    ->group(function () {
        Route::post('/internal/usage-events', [UsageEventApiController::class, 'store'])
            ->name('api.v1.internal.usage-events.store');
});

require __DIR__.'/auth.php';
