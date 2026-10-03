<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\ValidateInvoiceDraftRequest;
use App\Models\Customer;
use App\Models\ProductService;
use App\Services\Invoices\InvoiceDraftContextBuilder;
use App\Services\Invoices\Validation\ValidationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceValidationController extends Controller
{
    public function __construct(
        private readonly InvoiceDraftContextBuilder $builder,
        private readonly ValidationEngine $engine,
    ) {}

    /**
     * No real wizard state exists yet (steps 1-3 are still the Fase 2
     * static prototype), so this builds a real, representative draft from
     * the company's own data — its "Consumidor Final" customer and up to
     * two of its own active products — purely so the screen has something
     * genuine to validate without requiring a full session-backed wizard.
     */
    public function show(Request $request): View
    {
        $company = $request->user()->company;

        $customer = Customer::where('is_final_consumer', true)->first() ?? Customer::first();

        $products = ProductService::query()->availableForInvoicing()->limit(2)->get();

        $draftPayload = [
            'customer_id' => $customer?->id,
            'items' => $products->map(static fn (ProductService $product): array => [
                'product_service_id' => $product->id,
                'description' => $product->name,
                'unit' => $product->unit,
                'quantity' => '1',
                'unit_price' => (string) $product->price,
                'discount_percent' => '0',
                'tax_ids' => null,
            ])->all(),
        ];

        $context = $this->builder->build($company, $draftPayload);
        $validation = $this->engine->run($context);

        return view('invoices.validation', [
            'draftPayload' => $draftPayload,
            'calculation' => $context->calculation,
            'validation' => $validation,
        ]);
    }

    public function store(ValidateInvoiceDraftRequest $request): JsonResponse
    {
        $context = $this->builder->build($request->user()->company, $request->validated());
        $validation = $this->engine->run($context);

        return response()->json([
            'calculation' => $context->calculation->toArray(),
            'validation' => $validation->toArray(),
        ]);
    }
}
