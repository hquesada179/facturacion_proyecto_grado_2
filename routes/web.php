<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CreditNotes\CreditNoteController;
use App\Http\Controllers\CreditNotes\CreditNotePdfController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Documents\DocumentVerificationController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoiceDraftController;
use App\Http\Controllers\Invoices\InvoiceDraftValidationController;
use App\Http\Controllers\Invoices\InvoicePdfController;
use App\Http\Controllers\Invoices\InvoiceValidationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PrototypeController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Settings\BillingSettingsController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\NumberingResolutionController;
use App\Http\Controllers\Settings\TaxController;
use App\Http\Controllers\TraceabilityController;
use App\Models\CreditNote;
use App\Support\PrototypeScreens;
use Illuminate\Support\Facades\Route;

// Public marketing landing. Previously `/` was a dead alias for the
// dashboard inside the `auth` group (nothing in the app ever referenced
// route('home') — every internal link already points at route('dashboard')
// directly), so repurposing it here doesn't change any authenticated
// navigation. An already-logged-in visitor is bounced straight to the
// real dashboard instead of seeing marketing copy.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/registro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/registro', [RegisteredUserController::class, 'store']);

    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Email verification (OTP) for freshly self-registered accounts. Kept
// under plain `auth` (not `guest`, not `verified`) — the user is already
// logged in right after registering and is precisely the one who still
// needs to pass through here. `verification.notice`/`verification.send`
// match Laravel's own built-in route names on purpose, so the `verified`
// middleware on the onboarding group below redirects here by name
// exactly the way it would for the framework's own link-based flow.
Route::middleware('auth')->group(function (): void {
    Route::get('/verificar-correo', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/verificar-correo', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:otp-verify')
        ->name('verification.verify');
    Route::post('/verificar-correo/reenviar', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:otp-resend')
        ->name('verification.send');
});

Route::get('/verificar-documento/{token}', [DocumentVerificationController::class, 'show'])
    ->name('documents.verify');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Onboarding wizard — reachable manually only for now (no automatic
    // post-login redirect yet, see OnboardingController's own class
    // comment). Not a mega-route: each step is its own thin action, all
    // persistence delegates to the real Company/Customer/ProductService
    // FormRequests and Services already used by Configuración/Clientes/
    // Productos. company_id isolation is enforced the same way it is
    // everywhere else in the app — the model-level CompanyScope/
    // BelongsToCompany trait, plus CompanyPolicy::update for the company
    // steps — not a new middleware.
    //
    // `verified` IS new here, scoped to this group only (not the whole
    // `auth` group above, not dashboard/login): it stops a freshly
    // self-registered user from typing /onboarding before finishing the
    // OTP step. Every pre-existing/factory/seeded user already has
    // email_verified_at set, so this has zero effect on them — only an
    // unverified new registration is ever redirected (to
    // 'verification.notice', see EmailVerificationController) by this.
    Route::prefix('onboarding')->name('onboarding.')->middleware('verified')->group(function (): void {
        Route::get('/', [OnboardingController::class, 'show'])->name('show');
        Route::post('/start', [OnboardingController::class, 'start'])->name('start');
        Route::put('/company', [OnboardingController::class, 'updateCompany'])->name('company.update');
        Route::put('/billing', [OnboardingController::class, 'updateBilling'])->name('billing.update');
        Route::post('/customer', [OnboardingController::class, 'storeCustomer'])->name('customer.store');
        Route::post('/customer/demo', [OnboardingController::class, 'demoCustomer'])->name('customer.demo');
        Route::post('/product', [OnboardingController::class, 'storeProduct'])->name('product.store');
        Route::post('/product/demo', [OnboardingController::class, 'demoProduct'])->name('product.demo');
        Route::post('/data/continue', [OnboardingController::class, 'continueFromData'])->name('data.continue');
        Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    });

    Route::get('/reportes', [ReportsController::class, 'index'])
        ->middleware('can:view-reports')
        ->name('reports.index');
    Route::get('/reportes/errores-productividad', [ReportsController::class, 'errors'])
        ->middleware('can:view-reports')
        ->name('reports.errors');
    Route::get('/reportes/exportar', [ReportsController::class, 'export'])
        ->middleware('can:view-reports')
        ->name('reports.export');
    Route::get('/asistente', [AssistantController::class, 'index'])
        ->middleware('can:use-assistant')
        ->name('assistant.index');
    Route::post('/asistente/mensaje', [AssistantController::class, 'message'])
        ->middleware(['can:use-assistant', 'throttle:assistant'])
        ->name('assistant.message');
    Route::post('/asistente/confirmar', [AssistantController::class, 'confirm'])
        ->middleware(['can:use-assistant', 'throttle:assistant', 'throttle:assistant-write'])
        ->name('assistant.confirm');
    Route::post('/asistente/cancelar', [AssistantController::class, 'cancel'])
        ->middleware(['can:use-assistant', 'throttle:assistant', 'throttle:assistant-write'])
        ->name('assistant.cancel');
    Route::post('/asistente/mensajes/{assistantMessage}/feedback', [AssistantController::class, 'feedback'])
        ->middleware(['can:use-assistant', 'throttle:assistant'])
        ->name('assistant.feedback');

    // Remaining prototype-only screens (no real backing module yet): invoice
    // cancellation (superseded by the credit-note void flow) and the
    // settings sub-pages explicitly scoped for a later phase.
    foreach (PrototypeScreens::routes() as $screen) {
        $route = Route::get($screen['uri'], [PrototypeController::class, 'show'])
            ->defaults('screen', $screen['key'])
            ->name($screen['route']);

        if (isset($screen['permission'])) {
            $route->middleware('can:'.$screen['permission']);
        }
    }

    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('/trazabilidad', [TraceabilityController::class, 'index'])
        ->middleware('can:view-traceability')
        ->name('traceability.index');

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

    Route::get('/facturas', [InvoiceController::class, 'index'])->name('invoices.index');

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
    Route::post('/facturas/{invoice}/productos/rapido', [InvoiceDraftController::class, 'quickCreateProduct'])
        ->middleware('can:manage-invoicing')
        ->name('invoices.draft.items.quick-create-product');

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
    Route::get('/facturas/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf.show');
    Route::get('/facturas/{invoice}/pdf/descargar', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
    Route::post('/facturas/{invoice}/enviar-simulada', [InvoicePdfController::class, 'send'])->name('invoices.delivery.send');

    Route::get('/notas-credito', [CreditNoteController::class, 'index'])->name('credit-notes.index');
    Route::get('/notas-credito/crear', [CreditNoteController::class, 'create'])
        ->middleware('can:create,'.CreditNote::class)
        ->name('credit-notes.create');
    Route::post('/notas-credito', [CreditNoteController::class, 'store'])
        ->middleware('can:create,'.CreditNote::class)
        ->name('credit-notes.store');
    Route::get('/notas-credito/{creditNote}/revisar', [CreditNoteController::class, 'review'])->name('credit-notes.review');
    Route::post('/notas-credito/{creditNote}/validar', [CreditNoteController::class, 'validateDraft'])->name('credit-notes.validate');
    Route::post('/notas-credito/{creditNote}/emitir', [CreditNoteController::class, 'issue'])->name('credit-notes.issue');
    Route::post('/notas-credito/{creditNote}/descartar', [CreditNoteController::class, 'discard'])->name('credit-notes.discard');
    Route::post('/notas-credito/{creditNote}/reintentar', [CreditNoteController::class, 'retry'])->name('credit-notes.retry');
    Route::get('/notas-credito/{creditNote}/pdf', [CreditNotePdfController::class, 'show'])->name('credit-notes.pdf.show');
    Route::get('/notas-credito/{creditNote}/pdf/descargar', [CreditNotePdfController::class, 'download'])->name('credit-notes.pdf.download');
    Route::get('/notas-credito/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
});
