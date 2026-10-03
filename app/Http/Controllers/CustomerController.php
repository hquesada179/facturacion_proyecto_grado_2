<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Tax;
use App\Services\Tax\NitDvCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->search($request->string('q')->toString() ?: null)
            ->status($request->string('status')->toString() ?: null)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $finalConsumer = Customer::where('is_final_consumer', true)->first();

        return view('customers.index', [
            'customers' => $customers,
            'finalConsumer' => $finalConsumer,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'customer' => new Customer,
            'taxes' => Tax::query()->currentlyValid()->availableFor(auth()->user()->company_id)->get(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validated();

        $customer = new Customer($data);
        $customer->dv = $data['identification_type'] === 'NIT'
            ? (string) NitDvCalculator::calculate($data['identification_number'])
            : null;
        $customer->save();

        return redirect()->route('customers.show', $customer)->with('status', 'Cliente creado correctamente.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        return view('customers.show', [
            'customer' => $customer->load('tax'),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'taxes' => Tax::query()->currentlyValid()->availableFor($customer->company_id)->get(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validated();

        $customer->fill($data);
        $customer->dv = $data['identification_type'] === 'NIT'
            ? (string) NitDvCalculator::calculate($data['identification_number'])
            : null;
        $customer->save();

        return redirect()->route('customers.show', $customer)->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->invoices()->exists()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', 'No se puede eliminar un cliente con documentos asociados. Puedes inactivarlo.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Cliente eliminado correctamente.');
    }

    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update(['status' => $customer->status === 'active' ? 'inactive' : 'active']);

        return redirect()->back()->with('status', 'Estado del cliente actualizado correctamente.');
    }
}
