<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoiceDraftController;
use App\Http\Controllers\Invoices\InvoiceDraftValidationController;
use App\Http\Controllers\Invoices\InvoiceValidationController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\Settings\BillingSettingsController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\NumberingResolutionController;
use App\Http\Controllers\Settings\TaxController;
use App\Support\PrototypeScreens;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::view('/onboarding/empresa', 'onboarding.company')->name('onboarding.company');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [PrototypeController::class, 'show'])
        ->defaults('screen', 'dashboard')
        ->name('home');

    foreach (PrototypeScreens::routes() as $screen) {
        $route = Route::get($screen['uri'], [PrototypeController::class, 'show'])
            ->defaults('screen', $screen['key'])
            ->name($screen['route']);

        if (isset($screen['permission'])) {
            $route->middleware('can:'.$screen['permission']);
        }
    }

    Route::get('/configuracion/empresa', [CompanyController::class, 'edit'])
        ->middleware('can:manage-company')
        ->name('settings.company');
    Route::put('/configuracion/empresa', [CompanyController::class, 'update'])
        ->middleware('can:manage-company')
        ->name('settings.company.update');

    Route::get('/configuracion/facturacion-impuestos', [BillingSettingsController::class, 'index'])
        ->middleware('can:manage-numbering')
        ->name('settings.billing');
    Route::get('/configuracion/facturacion-impuestos/resoluciones/nueva', [NumberingResolutionController::class, 'create'])
        ->middleware('can:manage-numbering')
        ->name('settings.billing.numbering.create');
    Route::post('/configuracion/facturacion-impuestos/resoluciones', [NumberingResolutionController::class, 'store'])
        ->middleware('can:manage-numbering')
        ->name('settings.billing.numbering.store');
    Route::get('/configuracion/facturacion-impuestos/resoluciones/{numberingResolution}/editar', [NumberingResolutionController::class, 'edit'])
        ->middleware('can:manage-numbering')
        ->name('settings.billing.numbering.edit');
    Route::put('/configuracion/facturacion-impuestos/resoluciones/{numberingResolution}', [NumberingResolutionController::class, 'update'])
        ->middleware('can:manage-numbering')
        ->name('settings.billing.numbering.update');
    Route::patch('/configuracion/facturacion-impuestos/impuestos/{tax}/estado', [TaxController::class, 'toggle'])
        ->middleware('can:manage-taxes')
        ->name('settings.billing.taxes.toggle');

    Route::get('/clientes', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/clientes/nuevo', [CustomerController::class, 'create'])
        ->middleware('can:manage-invoicing')
        ->name('customers.create');
    Route::post('/clientes', [CustomerController::class, 'store'])
        ->middleware('can:manage-invoicing')
        ->name('customers.store');
    Route::get('/clientes/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/clientes/{customer}/editar', [CustomerController::class, 'edit'])
        ->middleware('can:manage-invoicing')
        ->name('customers.edit');
    Route::put('/clientes/{customer}', [CustomerController::class, 'update'])
        ->middleware('can:manage-invoicing')
        ->name('customers.update');
    Route::delete('/clientes/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('can:manage-invoicing')
        ->name('customers.destroy');
    Route::patch('/clientes/{customer}/estado', [CustomerController::class, 'toggleStatus'])
        ->middleware('can:manage-invoicing')
        ->name('customers.toggle-status');

    Route::get('/productos-servicios', [ProductServiceController::class, 'index'])->name('products.index');
    Route::get('/productos-servicios/nuevo', [ProductServiceController::class, 'create'])
        ->middleware('can:manage-invoicing')
        ->name('products.create');
    Route::post('/productos-servicios', [ProductServiceController::class, 'store'])
        ->middleware('can:manage-invoicing')
        ->name('products.store');
    Route::get('/productos-servicios/{product}', [ProductServiceController::class, 'show'])->name('products.show');
    Route::get('/productos-servicios/{product}/editar', [ProductServiceController::class, 'edit'])
        ->middleware('can:manage-invoicing')
        ->name('products.edit');
    Route::put('/productos-servicios/{product}', [ProductServiceController::class, 'update'])
        ->middleware('can:manage-invoicing')
        ->name('products.update');
    Route::delete('/productos-servicios/{product}', [ProductServiceController::class, 'destroy'])
        ->middleware('can:manage-invoicing')
        ->name('products.destroy');

    Route::get('/facturas/nueva/validacion', [InvoiceValidationController::class, 'show'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.create.validation');
    Route::post('/facturas/validar', [InvoiceValidationController::class, 'store'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.validate');

    // Real, persisted invoice wizard (Fase 4). "invoices.create.customer"
    // keeps its historic name/URI (sidebar/dashboard CTAs already link to
    // it) but now creates a real draft and redirects into it.
    Route::get('/facturas/nueva/cliente', [InvoiceDraftController::class, 'create'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.create.customer');

    Route::get('/facturas/{invoice}/cliente', [InvoiceDraftController::class, 'customer'])
        ->name('invoices.draft.customer');
    Route::post('/facturas/{invoice}/cliente', [InvoiceDraftController::class, 'updateCustomer'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.customer.update');
    Route::post('/facturas/{invoice}/cliente/rapido', [InvoiceDraftController::class, 'quickCreateCustomer'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.customer.quick-create');

    Route::get('/facturas/{invoice}/productos', [InvoiceDraftController::class, 'items'])
        ->name('invoices.draft.items');
    Route::post('/facturas/{invoice}/productos', [InvoiceDraftController::class, 'storeItem'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.items.store');
    Route::put('/facturas/{invoice}/productos/{item}', [InvoiceDraftController::class, 'updateItem'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.items.update');
    Route::delete('/facturas/{invoice}/productos/{item}', [InvoiceDraftController::class, 'destroyItem'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.items.destroy');

    Route::get('/facturas/{invoice}/resumen', [InvoiceDraftController::class, 'summary'])
        ->name('invoices.draft.summary');
    Route::post('/facturas/{invoice}/resumen', [InvoiceDraftController::class, 'updateSummary'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.summary.update');

    Route::get('/facturas/{invoice}/validacion', [InvoiceDraftValidationController::class, 'show'])
        ->name('invoices.draft.validation');
    Route::post('/facturas/{invoice}/validar', [InvoiceDraftValidationController::class, 'validate'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.validate');
    Route::post('/facturas/{invoice}/emitir', [InvoiceDraftValidationController::class, 'issue'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.issue');

    Route::post('/facturas/{invoice}/descartar', [InvoiceDraftController::class, 'discard'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.discard');

    Route::get('/facturas/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
});
