<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMetricsService;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(AdminMetricsService $adminMetricsService): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'metrics' => $adminMetricsService->getSummaryMetrics(),
        ]);
    }
}
