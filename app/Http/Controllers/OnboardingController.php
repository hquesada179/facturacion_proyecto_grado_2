<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Onboarding\OnboardingNumberingRequest;
use App\Http\Requests\Products\StoreProductServiceRequest;
use App\Http\Requests\Settings\UpdateCompanyRequest;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\Tax;
use App\Services\Customers\CustomerService;
use App\Services\Numbering\NumberingService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Products\ProductCatalogService;
use App\Services\Tax\NitDvCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Thin, one-action-per-step controller on purpose (per the task's own
 * "evita una mega-ruta que procese todo"): every method here either
 * delegates validation to the exact same FormRequest the real
 * Configuración/Clientes/Productos screens already use, or delegates
 * persistence to OnboardingService/CustomerService/ProductCatalogService.
 * Nothing here invents a parallel validation or creation path.
 *
 * Not wired into the login redirect yet (explicit instruction for this
 * phase) — reachable only by visiting /onboarding directly.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly CustomerService $customers,
        private readonly ProductCatalogService $products,
        private readonly NumberingService $numbering,
    ) {}

    public function show(Request $request): View
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        if ($company->onboarding_started_at === null) {
            return view('onboarding.welcome', ['company' => $company]);
        }

        $step = $this->onboarding->resolveDisplayStep($company, $request->query('step'));

        return match ($step) {
            'billing' => view('onboarding.billing', [
                'company' => $company,
                'numberingResolution' => $this->numbering->activeResolutionFor($company, DocumentType::Invoice),
            ]),
            'data' => view('onboarding.data', [
                'company' => $company,
                'hasCustomer' => Customer::withoutGlobalScopes()->where('company_id', $company->id)->exists(),
                'hasProduct' => ProductService::withoutGlobalScopes()->where('company_id', $company->id)->exists(),
                'hasDemoCustomer' => Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('identification_number', OnboardingService::DEMO_CUSTOMER_IDENTIFICATION)->exists(),
                'hasDemoProduct' => ProductService::withoutGlobalScopes()->where('company_id', $company->id)->where('sku', OnboardingService::DEMO_PRODUCT_SKU)->exists(),
                'taxes' => Tax::query()->currentlyValid()->availableFor($company->id)->get(),
            ]),
            'done' => view('onboarding.done', [
                'company' => $company,
                'customer' => Customer::withoutGlobalScopes()->where('company_id', $company->id)->latest('id')->first(),
                'product' => ProductService::withoutGlobalScopes()->where('company_id', $company->id)->latest('id')->first(),
            ]),
            default => view('onboarding.company', ['company' => $company]),
        };
    }

    public function start(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        $this->onboarding->start($company);

        return redirect()->route('onboarding.show');
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        $fields = ['name', 'legal_name', 'person_type', 'nit', 'email', 'phone', 'address', 'city', 'department', 'country'];
        $rules = Arr::only((new UpdateCompanyRequest)->rules(), $fields);
        $data = $request->validate($rules);

        $company->fill($data);
        $company->nit_dv = (string) NitDvCalculator::calculate($data['nit']);
        $company->save();

        $this->onboarding->advanceStep($company, 'billing');

        return redirect()->route('onboarding.show');
    }

    public function updateBilling(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        $fields = ['currency', 'tax_regime', 'main_tax_id'];
        $rules = Arr::only((new UpdateCompanyRequest)->rules(), $fields);
        $data = $request->validate($rules);

        $company->fill($data);
        $company->save();

        $this->onboarding->advanceStep($company, 'data');

        return redirect()->route('onboarding.show');
    }

    /**
     * Optional, additive to the Billing step: lets a brand-new company
     * create its first simulated invoice numbering resolution inline,
     * instead of only discovering it's missing later at emission time.
     * Does not gate advancing past this step — updateBilling() above is
     * completely unchanged — this is purely a convenience shortcut to
     * the same creation path Configuración → Facturación/Numeración
     * already uses.
     */
    public function storeNumbering(OnboardingNumberingRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        $this->onboarding->ensureInitialNumberingResolution($company, $request->validated());

        return redirect()->route('onboarding.show', ['step' => 'billing']);
    }

    public function demoCustomer(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);
        $this->authorize('create', Customer::class);

        $this->onboarding->ensureDemoCustomer($company);

        return redirect()->route('onboarding.show', ['step' => 'data']);
    }

    public function storeCustomer(StoreCustomerRequest $request): RedirectResponse
    {
        $this->customers->createFromData($request->validated());

        return redirect()->route('onboarding.show', ['step' => 'data']);
    }

    public function demoProduct(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);
        $this->authorize('create', ProductService::class);

        $this->onboarding->ensureDemoProduct($company);

        return redirect()->route('onboarding.show', ['step' => 'data']);
    }

    public function storeProduct(StoreProductServiceRequest $request): RedirectResponse
    {
        $this->products->createFromData($request->validated(), $request->input('taxes', []));

        return redirect()->route('onboarding.show', ['step' => 'data']);
    }

    public function continueFromData(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        $this->onboarding->advanceStep($company, 'done');

        return redirect()->route('onboarding.show');
    }

    public function complete(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->authorize('update', $company);

        $this->onboarding->complete($company);

        if ($request->input('redirect') === 'tour') {
            return redirect()->route('dashboard', ['start_tour' => 1]);
        }

        return redirect()->route('dashboard');
    }
}
