<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductServiceRequest;
use App\Http\Requests\Products\UpdateProductServiceRequest;
use App\Models\ProductService;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductServiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductService::class);

        $products = ProductService::query()
            ->search($request->string('q')->toString() ?: null)
            ->status($request->string('status')->toString() ?: null)
            ->type($request->string('type')->toString() ?: null)
            ->with('taxes')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'filters' => $request->only(['q', 'status', 'type']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ProductService::class);

        return view('products.create', [
            'product' => new ProductService,
            'taxes' => Tax::query()->currentlyValid()->availableFor(auth()->user()->company_id)->get(),
        ]);
    }

    public function store(StoreProductServiceRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductService::class);

        $data = $request->validated();
        $data['tax_included'] = $request->boolean('tax_included');
        $taxes = $data['taxes'] ?? [];
        unset($data['taxes']);

        $product = ProductService::create($data);
        $product->taxes()->sync($taxes);

        return redirect()->route('products.show', $product)->with('status', 'Producto creado correctamente.');
    }

    public function show(ProductService $product): View
    {
        $this->authorize('view', $product);

        return view('products.show', [
            'product' => $product->load('taxes'),
        ]);
    }

    public function edit(ProductService $product): View
    {
        $this->authorize('update', $product);

        return view('products.edit', [
            'product' => $product->load('taxes'),
            'taxes' => Tax::query()->currentlyValid()->availableFor($product->company_id)->get(),
        ]);
    }

    public function update(UpdateProductServiceRequest $request, ProductService $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $data['tax_included'] = $request->boolean('tax_included');
        $taxes = $data['taxes'] ?? [];
        unset($data['taxes']);

        $product->update($data);
        $product->taxes()->sync($taxes);

        return redirect()->route('products.show', $product)->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(ProductService $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->invoiceItems()->exists()) {
            return redirect()->route('products.show', $product)
                ->with('error', 'No se puede eliminar un producto con movimientos asociados. Puedes descontinuarlo.');
        }

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Producto eliminado correctamente.');
    }
}
