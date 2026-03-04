<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\OrganizationSwitchController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

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
});

require __DIR__.'/auth.php';
