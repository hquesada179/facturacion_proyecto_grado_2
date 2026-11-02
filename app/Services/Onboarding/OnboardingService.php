<?php

namespace App\Services\Onboarding;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InvoiceEvent;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Services\Numbering\NumberingService;
use App\Services\Products\ProductCatalogService;

/**
 * Everything the onboarding wizard needs beyond plain form validation:
 * step bookkeeping on the owning Company (never on User — see the
 * migration's own comment on why progress is company-level), idempotent
 * demo-data provisioning, and the real "primeros pasos" checklist.
 *
 * Deliberately thin: company field persistence and customer/product
 * creation still go through the exact same FormRequest/Service classes
 * the real Configuración/Clientes/Productos screens use
 * (OnboardingController injects them directly) — this class never
 * duplicates that validation or creation logic.
 */
class OnboardingService
{
    /**
     * Demo customer/product are recognized by a fixed identifier (not a
     * flag column) so "already created?" is answered from real data,
     * exactly like CustomerService::ensureFinalConsumer() already does
     * for the final-consumer placeholder.
     */
    public const DEMO_CUSTOMER_IDENTIFICATION = '900555123';

    public const DEMO_PRODUCT_SKU = 'SERV-001';

    public function __construct(
        private readonly CustomerService $customers,
        private readonly ProductCatalogService $products,
        private readonly NumberingService $numbering,
    ) {}

    public const STEPS = ['company', 'billing', 'data', 'done'];

    public function start(Company $company): void
    {
        if ($company->onboarding_started_at !== null) {
            return;
        }

        $company->onboarding_started_at = now();
        $company->onboarding_step = self::STEPS[0];
        $company->save();
    }

    public function advanceStep(Company $company, string $step): void
    {
        if (! in_array($step, self::STEPS, true)) {
            return;
        }

        $company->onboarding_step = $step;
        $company->save();
    }

    public function complete(Company $company): void
    {
        $company->onboarding_completed_at = now();
        $company->onboarding_step = 'done';
        $company->save();
    }

    /**
     * Resolves which step to actually display: never further than the
     * company's own persisted progress (so a stale bookmark or a typed
     * ?step= can't skip ahead), but a lower step is always allowed —
     * that's the "retroceder visualmente" requirement, and it needs no
     * extra write since every earlier step's data is already saved.
     */
    public function resolveDisplayStep(Company $company, ?string $requested): string
    {
        $persisted = $company->onboarding_step ?? self::STEPS[0];
        $persistedIndex = array_search($persisted, self::STEPS, true) ?: 0;

        if ($requested === null || ! in_array($requested, self::STEPS, true)) {
            return $persisted;
        }

        $requestedIndex = array_search($requested, self::STEPS, true);

        return $requestedIndex <= $persistedIndex ? $requested : $persisted;
    }

    /**
     * Idempotent: a second visit to the "datos de demostración" option
     * never creates a duplicate customer, mirroring
     * CustomerService::ensureFinalConsumer()'s own pattern exactly.
     */
    public function ensureDemoCustomer(Company $company): Customer
    {
        $existing = Customer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('identification_number', self::DEMO_CUSTOMER_IDENTIFICATION)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->customers->createFromData([
            'company_id' => $company->id,
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => self::DEMO_CUSTOMER_IDENTIFICATION,
            'name' => 'Comercial Andina SAS',
            'email' => 'contacto@andina.com',
            'phone' => '6015550100',
            'address' => 'Calle 10 # 5-23',
            'city' => 'Bogotá',
            'country' => 'Colombia',
            'status' => 'active',
        ]);
    }

    /**
     * Idempotent by sku, same reasoning as ensureDemoCustomer().
     */
    public function ensureDemoProduct(Company $company): ProductService
    {
        $existing = ProductService::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('sku', self::DEMO_PRODUCT_SKU)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $iva19 = Tax::query()
            ->currentlyValid()
            ->availableFor($company->id)
            ->where('code', 'IVA19')
            ->first();

        return $this->products->createFromData([
            'company_id' => $company->id,
            'sku' => self::DEMO_PRODUCT_SKU,
            'name' => 'Asesoría contable mensual',
            'type' => 'service',
            'unit' => 'servicio',
            'price' => 450000,
            'status' => 'active',
            'tax_included' => false,
            'is_inventoried' => false,
        ], $iva19 !== null ? [$iva19->id] : []);
    }

    /**
     * Idempotent, same pattern as ensureDemoCustomer()/ensureDemoProduct():
     * a company can only ever have one *active* resolution per document
     * type in practice (that's what blocks invoice issuance), so if one
     * already exists it's returned as-is — the onboarding Billing step
     * must never create a second, competing resolution.
     */
    public function ensureInitialNumberingResolution(Company $company, array $data): NumberingResolution
    {
        $existing = $this->numbering->activeResolutionFor($company, DocumentType::Invoice);

        if ($existing !== null) {
            return $existing;
        }

        return $company->numberingResolutions()->create([
            'document_type' => DocumentType::Invoice,
            'authorization_number_simulated' => sprintf('SIM-%s-%06d', strtoupper((string) $data['prefix']), (int) $data['range_from']),
            'prefix' => $data['prefix'],
            'range_from' => $data['range_from'],
            'range_to' => $data['range_to'],
            'current_consecutive' => $data['range_from'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            // Same literal placeholder DatabaseSeeder's own demo
            // resolution uses — one shared convention for "this is a
            // simulated technical key", not a second one invented here.
            'simulated_technical_key' => 'DOCUMENTO-DE-PRUEBA-SIN-VALIDEZ-TRIBUTARIA',
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Real data only — no stored "cliente_creado"/"producto_creado"
     * booleans, per the task's own instruction. Each condition is a
     * single exists() check, so this stays O(1) queries regardless of
     * how much data the company has.
     */
    public function checklistFor(User $user): array
    {
        $company = $user->company;

        $hasCompanyProfile = $company !== null
            && filled($company->legal_name)
            && filled($company->nit);

        $hasCustomer = $company !== null && Customer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->exists();

        $hasProduct = $company !== null && ProductService::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->exists();

        // Issued only — a draft (or any non-issued status) never counts
        // as "created". Issuing an invoice already structurally requires
        // a real customer, product and an active numbering resolution
        // (see IssueInvoiceService), so this one condition is equivalent
        // to — not weaker than — checking all of those separately.
        $hasInvoice = $company !== null && \App\Models\Invoice::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', InvoiceStatus::Issued)
            ->exists();

        $hasTraceability = $company !== null && InvoiceEvent::query()
            ->whereHas('invoice', fn ($query) => $query->withoutGlobalScopes()->where('company_id', $company->id))
            ->exists();

        $items = [
            ['key' => 'company', 'label' => 'Configurar empresa', 'done' => $hasCompanyProfile],
            ['key' => 'customer', 'label' => 'Crear un cliente', 'done' => $hasCustomer],
            ['key' => 'product', 'label' => 'Crear un producto', 'done' => $hasProduct],
            ['key' => 'invoice', 'label' => 'Crear primera factura', 'done' => $hasInvoice],
            ['key' => 'traceability', 'label' => 'Consultar trazabilidad', 'done' => $hasTraceability],
        ];

        $doneCount = count(array_filter($items, fn (array $item): bool => $item['done']));

        return [
            'items' => $items,
            'doneCount' => $doneCount,
            'total' => count($items),
            'percent' => (int) round(($doneCount / count($items)) * 100),
        ];
    }
}
