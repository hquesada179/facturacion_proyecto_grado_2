<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NumberingResolutionRequest;
use App\Models\NumberingResolution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NumberingResolutionController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', NumberingResolution::class);

        return view('settings.billing.numbering-form', [
            'resolution' => new NumberingResolution,
            'formAction' => route('settings.billing.numbering.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(NumberingResolutionRequest $request): RedirectResponse
    {
        $this->authorize('create', NumberingResolution::class);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $request->user()->company->numberingResolutions()->create($data);

        return redirect()->route('settings.billing')->with('status', 'Resolución de numeración creada correctamente.');
    }

    public function edit(Request $request, NumberingResolution $numberingResolution): View
    {
        $this->authorize('update', $numberingResolution);

        return view('settings.billing.numbering-form', [
            'resolution' => $numberingResolution,
            'formAction' => route('settings.billing.numbering.update', $numberingResolution),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(NumberingResolutionRequest $request, NumberingResolution $numberingResolution): RedirectResponse
    {
        $this->authorize('update', $numberingResolution);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $numberingResolution->update($data);

        return redirect()->route('settings.billing')->with('status', 'Resolución de numeración actualizada correctamente.');
    }
}
