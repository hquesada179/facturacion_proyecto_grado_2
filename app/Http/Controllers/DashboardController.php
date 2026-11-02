<?php

namespace App\Http\Controllers;

use App\Services\Onboarding\OnboardingService;
use App\Services\Reports\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $dashboard, OnboardingService $onboarding): View
    {
        return view('dashboard.index', [
            ...$dashboard->dataFor($request->user()),
            'checklist' => $onboarding->checklistFor($request->user()),
        ]);
    }
}
