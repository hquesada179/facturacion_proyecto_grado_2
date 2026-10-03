<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NumberingResolution;
use App\Models\Tax;
use App\Services\Numbering\NumberingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingSettingsController extends Controller
{
    public function __construct(private NumberingService $numberingService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', NumberingResolution::class);

        $resolutions = $request->user()->company->numberingResolutions()
            ->orderByDesc('is_active')
            ->orderByDesc('valid_from')
            ->get()
            ->map(function (NumberingResolution $resolution) {
                $resolution->setAttribute('near_expiry', $this->numberingService->isNearExpiry($resolution));
                $resolution->setAttribute('near_exhaustion', $this->numberingService->isNearExhaustion($resolution));

                return $resolution;
            });

        $taxes = Tax::query()->availableFor($request->user()->company_id)->orderBy('code')->get();

        return view('settings.billing.index', [
            'resolutions' => $resolutions,
            'taxes' => $taxes,
            'expiringCount' => $resolutions->where('near_expiry', true)->count(),
            'exhaustingCount' => $resolutions->where('near_exhaustion', true)->count(),
        ]);
    }
}
