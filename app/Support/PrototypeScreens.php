<?php

namespace App\Support;

use App\Models\Invoice;

final class PrototypeScreens
{
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Every remaining entry in all() is a genuine prototype-only screen
     * (no real backing module) — real features get their own controller
     * and are routed directly in routes/web.php instead.
     */
    public static function routes(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $screen): bool => isset($screen['uri'], $screen['route'])
        ));
    }

    public static function navigation(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Inicio', 'icon' => 'dashboard', 'route' => 'dashboard'],
            ['key' => 'invoices', 'label' => 'Facturas', 'icon' => 'description', 'route' => 'invoices.index'],
            ['key' => 'customers', 'label' => 'Clientes', 'icon' => 'group', 'route' => 'customers.index'],
            ['key' => 'products', 'label' => 'Productos y servicios', 'icon' => 'inventory_2', 'route' => 'products.index'],
            ['key' => 'credit-notes', 'label' => 'Notas crédito', 'icon' => 'assignment_return', 'route' => 'credit-notes.index'],
            ['key' => 'reports', 'label' => 'Reportes', 'icon' => 'analytics', 'route' => 'reports.index'],
            ['key' => 'traceability', 'label' => 'Trazabilidad', 'icon' => 'history', 'route' => 'traceability.index', 'permission' => 'view-traceability'],
            ['key' => 'settings', 'label' => 'Configuración', 'icon' => 'settings', 'route' => 'settings.company', 'permission' => 'manage-company'],
        ];
    }

    public static function settingsTabs(): array
    {
        return [
            ['label' => 'Datos de empresa', 'route' => 'settings.company'],
            ['label' => 'Facturación e impuestos', 'route' => 'settings.billing'],
            ['label' => 'Usuarios', 'route' => 'settings.users'],
            ['label' => 'Seguridad', 'route' => 'settings.security'],
            ['label' => 'Preferencias', 'route' => 'settings.preferences'],
        ];
    }

    /**
     * Real, persisted wizard (Fase 4): each step needs the current draft's
     * id, so this returns ready-made hrefs instead of bare route names —
     * see <x-invoice-stepper>. $invoice is only null for the standalone
     * Fase 3 demo page (/facturas/nueva/validacion), which has no draft to
     * link steps 2-3 to.
     */
    public static function invoiceSteps(?Invoice $invoice = null): array
    {
        if ($invoice === null) {
            return [
                ['number' => 1, 'label' => 'Cliente', 'href' => route('invoices.create.customer')],
                ['number' => 2, 'label' => 'Productos y servicios', 'href' => '#'],
                ['number' => 3, 'label' => 'Resumen y totales', 'href' => '#'],
                ['number' => 4, 'label' => 'Validación', 'href' => route('invoices.create.validation')],
            ];
        }

        return [
            ['number' => 1, 'label' => 'Cliente', 'href' => route('invoices.draft.customer', $invoice)],
            ['number' => 2, 'label' => 'Productos y servicios', 'href' => route('invoices.draft.items', $invoice)],
            ['number' => 3, 'label' => 'Resumen y totales', 'href' => route('invoices.draft.summary', $invoice)],
            ['number' => 4, 'label' => 'Validación', 'href' => route('invoices.draft.validation', $invoice)],
        ];
    }

    /**
     * Screens that still have no real backing module. Every feature that
     * gained a real controller/view (dashboard, invoices, credit notes,
     * reports, assistant, traceability, profile) was removed from here —
     * see routes/web.php for their real routes.
     */
    public static function all(): array
    {
        return [
            'invoices.cancel' => self::formPage('invoices.cancel', '/facturas/FV-00156/anular', 'invoices.cancel', 'Anular factura FV-00156', 'Anulación simulada con motivo obligatorio y confirmación del usuario.', 'invoices', 'anular_factura_fv_00156_facturapro_col', null, [self::section('Motivo de anulación', [['label' => 'Tipo de motivo', 'value' => 'Error en datos del cliente', 'type' => 'select'], ['label' => 'Justificación', 'value' => 'El NIT del cliente debe corregirse antes de emitir un nuevo documento.', 'type' => 'textarea']])]) + ['permission' => 'manage-invoicing'],
            'settings.users' => self::settingsPage('settings.users', '/configuracion/usuarios', 'settings.users', 'Usuarios', 'Roles administrativos previstos para la siguiente fase.', 'configuraci_n_usuarios_facturapro_col', self::usersSettings()) + ['permission' => 'manage-company'],
            'settings.security' => self::settingsPage('settings.security', '/configuracion/seguridad', 'settings.security', 'Seguridad', 'Preferencias de acceso y acciones críticas.', 'configuraci_n_seguridad_facturapro_col', self::securitySettings()) + ['permission' => 'manage-company'],
            'settings.preferences' => self::settingsPage('settings.preferences', '/configuracion/preferencias', 'settings.preferences', 'Preferencias', 'Ajustes visuales y del asistente contextual.', 'configuraci_n_preferencias_facturapro_col', self::preferencesSettings()) + ['permission' => 'manage-company'],
        ];
    }

    private static function formPage(string $key, string $uri, string $route, string $title, string $description, string $active, ?string $source, ?int $step, array $sections, ?array $table = null): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'active', 'source', 'step', 'sections', 'table') + ['eyebrow' => ucfirst(str_replace('-', ' ', $active)), 'template' => 'form', 'assistant' => true];
    }

    private static function settingsPage(string $key, string $uri, string $route, string $title, string $description, string $source, array $sections): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'source', 'sections') + ['eyebrow' => 'Configuración', 'active' => 'settings', 'template' => 'settings', 'assistant' => true];
    }

    private static function section(string $title, array $fields): array
    {
        return compact('title', 'fields');
    }

    private static function usersSettings(): array
    {
        return [self::section('Usuarios base', [['label' => 'Administrador', 'value' => 'admin@empresa.com.co'], ['label' => 'Auxiliar de facturación', 'value' => 'auxiliar@empresa.com.co'], ['label' => 'Rol por defecto', 'value' => 'Facturación', 'type' => 'select']])];
    }

    private static function securitySettings(): array
    {
        return [self::section('Acceso', [['label' => 'Confirmar acciones críticas', 'value' => 'Activado', 'type' => 'select'], ['label' => 'Tiempo de sesión', 'value' => '120 minutos'], ['label' => 'Registro de trazabilidad', 'value' => 'Activado', 'type' => 'select']])];
    }

    private static function preferencesSettings(): array
    {
        return [self::section('Preferencias del asistente', [['label' => 'Asistente IA global', 'value' => 'Activado', 'type' => 'select'], ['label' => 'Tono de respuestas', 'value' => 'Operativo', 'type' => 'select'], ['label' => 'Sugerencias contextuales', 'value' => 'Mostrar en formularios', 'type' => 'select']])];
    }
}
