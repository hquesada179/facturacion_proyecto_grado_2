<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanyRequest;
use App\Services\Tax\NitDvCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $request->user()->company;

        $this->authorize('update', $company);

        return view('settings.company.edit', [
            'company' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $company = $request->user()->company;

        $this->authorize('update', $company);

        $data = $request->validated();
        $data['is_test_environment'] = $request->boolean('is_test_environment');
        $data['fiscal_responsibilities'] = $data['fiscal_responsibilities'] ?? [];

        $company->fill($data);
        $company->nit_dv = (string) NitDvCalculator::calculate($data['nit']);
        $company->save();

        return redirect()->route('settings.company')->with('status', 'Datos de la empresa actualizados correctamente.');
    }
}
