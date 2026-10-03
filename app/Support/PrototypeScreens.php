<?php

namespace App\Support;

use App\Models\Invoice;

final class PrototypeScreens
{
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function routes(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $screen): bool => isset($screen['uri'], $screen['route'])
                && ! str_starts_with($screen['route'], 'credit-notes.')
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

    public static function all(): array
    {
        return [
            'dashboard' => [
                'key' => 'dashboard',
                'uri' => '/dashboard',
                'route' => 'dashboard',
                'source' => 'dashboard_principal_facturapro_col',
                'title' => 'Dashboard principal',
                'eyebrow' => 'Inicio',
                'description' => 'Resumen de actividad, documentos recientes y accesos rápidos.',
                'active' => 'dashboard',
                'template' => 'dashboard',
                'metrics' => self::dashboardMetrics(),
                'quickActions' => [
                    ['label' => 'Nueva factura', 'icon' => 'add_circle', 'route' => 'invoices.create.customer', 'primary' => true],
                    ['label' => 'Nuevo cliente', 'icon' => 'person_add', 'route' => 'customers.create'],
                    ['label' => 'Nuevo producto', 'icon' => 'add_box', 'route' => 'products.create'],
                    ['label' => 'Consultar docs', 'icon' => 'search_insights', 'route' => 'traceability.index'],
                ],
                'table' => self::invoiceTable(),
            ],
            'invoices.index' => self::listPage(
                'invoices.index',
                '/facturas',
                'invoices.index',
                'Facturas',
                'Consulta documentos emitidos, borradores y validaciones simuladas.',
                'invoices',
                'listado_de_facturas_sistema_de_facturaci_n_inteligente',
                self::invoiceTable(),
                self::invoiceMetrics(),
                ['label' => 'Nueva factura', 'icon' => 'add', 'route' => 'invoices.create.customer']
            ),
            'invoices.cancel' => self::formPage('invoices.cancel', '/facturas/FV-00156/anular', 'invoices.cancel', 'Anular factura FV-00156', 'Anulación simulada con motivo obligatorio y confirmación del usuario.', 'invoices', 'anular_factura_fv_00156_facturapro_col', null, [self::section('Motivo de anulación', [['label' => 'Tipo de motivo', 'value' => 'Error en datos del cliente', 'type' => 'select'], ['label' => 'Justificación', 'value' => 'El NIT del cliente debe corregirse antes de emitir un nuevo documento.', 'type' => 'textarea']])]) + ['permission' => 'manage-invoicing'],
            'credit-notes.index' => self::listPage('credit-notes.index', '/notas-credito', 'credit-notes.index', 'Notas crédito', 'Documentos de corrección simulados asociados a facturas.', 'credit-notes', 'notas_cr_dito_facturapro_col', self::creditNotesTable(), self::creditNoteMetrics(), ['label' => 'Crear nota crédito', 'icon' => 'add', 'route' => 'credit-notes.create']),
            'credit-notes.create' => self::formPage('credit-notes.create', '/notas-credito/crear', 'credit-notes.create', 'Crear nota crédito', 'Selecciona factura origen y motivo de corrección.', 'credit-notes', 'crear_nota_cr_dito_facturapro_col', null, self::creditNoteFormSections(), self::lineItemsTable()) + ['permission' => 'manage-invoicing'],
            'credit-notes.review' => self::formPage('credit-notes.review', '/notas-credito/revisar', 'credit-notes.review', 'Revisar nota crédito', 'Verifica valores antes de finalizar la nota crédito.', 'credit-notes', 'revisar_nota_cr_dito_facturapro_col', null, self::creditNoteReviewSections(), self::validationTable()) + ['permission' => 'manage-invoicing'],
            'credit-notes.show' => self::detailPage('credit-notes.show', '/notas-credito/NC-0008', 'credit-notes.show', 'Detalle de nota crédito NC-0008', 'Consulta la relación entre nota crédito y factura origen.', 'credit-notes', 'detalle_de_nota_cr_dito_facturapro_col', self::creditNoteDetailCards(), self::lineItemsTable(), self::timeline()),
            'reports.index' => self::reportPage('reports.index', '/reportes', 'reports.index', 'Reportes e indicadores', 'Métricas operativas del prototipo académico.', 'reports', 'reportes_e_indicadores_facturapro_col'),
            'reports.errors' => self::reportPage('reports.errors', '/reportes/errores-productividad', 'reports.errors', 'Errores y productividad', 'Seguimiento de errores simulados y asistencia contextual.', 'reports', 'reporte_detallado_de_errores_y_productividad_facturapro_col'),
            'assistant.index' => ['key' => 'assistant.index', 'uri' => '/asistente', 'route' => 'assistant.index', 'source' => 'asistente_ia_vista_completa_facturapro_col', 'title' => 'Asistente IA', 'eyebrow' => 'Asistente', 'description' => 'Vista completa del asistente contextual del prototipo.', 'active' => 'assistant', 'template' => 'assistant', 'assistant' => false, 'wide' => true],
            'traceability.index' => self::listPage('traceability.index', '/trazabilidad', 'traceability.index', 'Historial documental', 'Eventos y acciones simuladas sobre documentos.', 'traceability', null, self::traceabilityTable(), self::traceabilityMetrics(), null) + ['permission' => 'view-traceability'],
            'settings.users' => self::settingsPage('settings.users', '/configuracion/usuarios', 'settings.users', 'Usuarios', 'Roles administrativos previstos para la siguiente fase.', 'configuraci_n_usuarios_facturapro_col', self::usersSettings()) + ['permission' => 'manage-company'],
            'settings.security' => self::settingsPage('settings.security', '/configuracion/seguridad', 'settings.security', 'Seguridad', 'Preferencias de acceso y acciones críticas.', 'configuraci_n_seguridad_facturapro_col', self::securitySettings()) + ['permission' => 'manage-company'],
            'settings.preferences' => self::settingsPage('settings.preferences', '/configuracion/preferencias', 'settings.preferences', 'Preferencias', 'Ajustes visuales y del asistente contextual.', 'configuraci_n_preferencias_facturapro_col', self::preferencesSettings()) + ['permission' => 'manage-company'],
            'profile.show' => self::detailPage('profile.show', '/perfil', 'profile.show', 'Perfil de usuario', 'Datos básicos del usuario administrador ficticio.', 'settings', 'perfil_de_usuario_facturapro_col', self::profileCards(), self::profileActivityTable(), self::timeline()),
        ];
    }

    private static function listPage(string $key, string $uri, string $route, string $title, string $description, string $active, ?string $source, array $table, array $metrics, ?array $primaryAction): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'active', 'source', 'table', 'metrics', 'primaryAction') + ['eyebrow' => ucfirst(str_replace('-', ' ', $active)), 'template' => 'list', 'assistant' => true];
    }

    private static function formPage(string $key, string $uri, string $route, string $title, string $description, string $active, ?string $source, ?int $step, array $sections, ?array $table = null): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'active', 'source', 'step', 'sections', 'table') + ['eyebrow' => ucfirst(str_replace('-', ' ', $active)), 'template' => 'form', 'assistant' => true];
    }

    private static function detailPage(string $key, string $uri, string $route, string $title, string $description, string $active, ?string $source, array $cards, array $table, array $timeline): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'active', 'source', 'cards', 'table', 'timeline') + ['eyebrow' => ucfirst(str_replace('-', ' ', $active)), 'template' => 'detail', 'assistant' => true];
    }

    private static function reportPage(string $key, string $uri, string $route, string $title, string $description, string $active, string $source): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'active', 'source') + ['eyebrow' => 'Reportes', 'template' => 'report', 'assistant' => true, 'metrics' => self::reportMetrics(), 'table' => self::reportTable()];
    }

    private static function settingsPage(string $key, string $uri, string $route, string $title, string $description, string $source, array $sections): array
    {
        return compact('key', 'uri', 'route', 'title', 'description', 'source', 'sections') + ['eyebrow' => 'Configuración', 'active' => 'settings', 'template' => 'settings', 'assistant' => true];
    }

    private static function section(string $title, array $fields): array
    {
        return compact('title', 'fields');
    }

    private static function dashboardMetrics(): array
    {
        return [
            ['label' => 'Facturas emitidas', 'value' => '156', 'icon' => 'receipt_long', 'tone' => 'primary'],
            ['label' => 'Tiempo promedio de emisión', 'value' => '2 min 45 s', 'icon' => 'timer', 'tone' => 'primary'],
            ['label' => 'Facturas pendientes', 'value' => '12', 'icon' => 'pending_actions', 'tone' => 'warning'],
            ['label' => 'Facturas con error', 'value' => '2', 'icon' => 'error_outline', 'tone' => 'error'],
            ['label' => 'Notas crédito', 'value' => '5', 'icon' => 'assignment_return', 'tone' => 'secondary'],
            ['label' => 'Consultas resueltas por IA', 'value' => '128', 'icon' => 'auto_awesome', 'tone' => 'secondary'],
        ];
    }

    private static function invoiceMetrics(): array
    {
        return [['label' => 'Emitidas este mes', 'value' => '38', 'icon' => 'receipt_long', 'tone' => 'primary'], ['label' => 'Pendientes', 'value' => '12', 'icon' => 'pending_actions', 'tone' => 'warning'], ['label' => 'Con error', 'value' => '2', 'icon' => 'error_outline', 'tone' => 'error']];
    }

    private static function creditNoteMetrics(): array
    {
        return [['label' => 'Notas creadas', 'value' => '5', 'icon' => 'assignment_return', 'tone' => 'secondary'], ['label' => 'En revisión', 'value' => '2', 'icon' => 'rate_review', 'tone' => 'warning'], ['label' => 'Finalizadas', 'value' => '3', 'icon' => 'check_circle', 'tone' => 'primary']];
    }

    private static function traceabilityMetrics(): array
    {
        return [['label' => 'Eventos registrados', 'value' => '214', 'icon' => 'history', 'tone' => 'primary'], ['label' => 'Acciones críticas', 'value' => '11', 'icon' => 'verified_user', 'tone' => 'warning'], ['label' => 'Interacciones IA', 'value' => '128', 'icon' => 'auto_awesome', 'tone' => 'secondary']];
    }

    private static function reportMetrics(): array
    {
        return [['label' => 'Productividad estimada', 'value' => '+32%', 'icon' => 'trending_up', 'tone' => 'primary'], ['label' => 'Errores reducidos', 'value' => '18%', 'icon' => 'rule', 'tone' => 'secondary'], ['label' => 'Consultas IA', 'value' => '128', 'icon' => 'auto_awesome', 'tone' => 'secondary']];
    }

    private static function invoiceTable(): array
    {
        return ['title' => 'Documentos recientes', 'headers' => ['N° Factura', 'Cliente', 'Fecha', 'Valor total', 'Estado'], 'rows' => [['FV-00156', 'Comercializadora Andina SAS', '2026-09-02', '$1.428.000', 'Validada'], ['FV-00155', 'Tienda La Esquina', '2026-09-01', '$450.000', 'Pendiente'], ['FV-00154', 'Servicios Logísticos', '2026-08-30', '$2.800.000', 'Con error'], ['FV-00153', 'Restaurante Gourmet', '2026-08-28', '$600.000', 'Anulada']]];
    }

    private static function creditNotesTable(): array
    {
        return ['title' => 'Notas crédito recientes', 'headers' => ['Nota', 'Factura origen', 'Cliente', 'Motivo', 'Estado'], 'rows' => [['NC-0008', 'FV-00150', 'Comercializadora Andina SAS', 'Corrección de valor', 'Finalizada'], ['NC-0007', 'FV-00147', 'Tienda La Esquina', 'Devolución parcial', 'En revisión'], ['NC-0006', 'FV-00144', 'Servicios Logísticos', 'Datos del cliente', 'Borrador']]];
    }

    private static function traceabilityTable(): array
    {
        return ['title' => 'Eventos documentales', 'headers' => ['Fecha', 'Documento', 'Usuario', 'Evento', 'Resultado'], 'rows' => [['2026-09-02 10:32', 'FV-00156', 'Administrador', 'Validación simulada', 'Aprobada'], ['2026-09-02 10:29', 'FV-00156', 'Administrador', 'Consulta IA contextual', 'Respondida'], ['2026-09-01 15:08', 'NC-0008', 'Administrador', 'Nota crédito finalizada', 'Registrada']]];
    }

    private static function lineItemsTable(): array
    {
        return ['title' => 'Productos y servicios agregados', 'headers' => ['Descripción', 'Cantidad', 'Valor unitario', 'IVA', 'Total'], 'rows' => [['Servicio mensual de soporte administrativo', '1', '$1.200.000', '$228.000', '$1.428.000']]];
    }

    private static function validationTable(): array
    {
        return ['title' => 'Validaciones del documento', 'headers' => ['Regla', 'Descripción', 'Estado', 'Acción sugerida'], 'rows' => [['Cliente', 'Identificación y correo completos', 'Correcto', 'Continuar'], ['Impuestos', 'IVA aplicado según configuración', 'Correcto', 'Continuar'], ['Totales', 'Subtotal + impuesto coincide con total', 'Correcto', 'Finalizar']]];
    }

    private static function reportTable(): array
    {
        return ['title' => 'Indicadores operativos', 'headers' => ['Indicador', 'Periodo actual', 'Periodo anterior', 'Tendencia'], 'rows' => [['Facturas emitidas', '38', '31', '+22%'], ['Errores detectados', '2', '7', '-71%'], ['Tiempo promedio', '2 min 45 s', '4 min 10 s', '+34%'], ['Consultas IA', '128', '96', '+33%']]];
    }

    private static function creditNoteDetailCards(): array
    {
        return [['label' => 'Factura origen', 'value' => 'FV-00150'], ['label' => 'Motivo', 'value' => 'Corrección de valor'], ['label' => 'Estado', 'value' => 'Finalizada'], ['label' => 'Total', 'value' => '$238.000']];
    }

    private static function profileCards(): array
    {
        return [['label' => 'Usuario', 'value' => 'Administrador'], ['label' => 'Correo', 'value' => 'admin@empresa.com.co'], ['label' => 'Rol', 'value' => 'Administrador'], ['label' => 'Empresa', 'value' => 'FacturaPro Demo SAS']];
    }

    private static function profileActivityTable(): array
    {
        return ['title' => 'Actividad reciente', 'headers' => ['Fecha', 'Módulo', 'Acción', 'Resultado'], 'rows' => [['2026-09-02', 'Facturas', 'Creó FV-00156', 'Registrada'], ['2026-09-02', 'Asistente IA', 'Consultó validaciones', 'Respondida']]];
    }

    private static function timeline(): array
    {
        return [['title' => 'Borrador creado', 'description' => 'El usuario inició el documento con datos ficticios.', 'time' => '10:24'], ['title' => 'Validación simulada', 'description' => 'El sistema verificó campos obligatorios y totales.', 'time' => '10:32'], ['title' => 'Consulta al asistente', 'description' => 'Se pidió orientación contextual antes de finalizar.', 'time' => '10:33']];
    }

    private static function creditNoteFormSections(): array
    {
        return [self::section('Factura origen', [['label' => 'Factura', 'value' => 'FV-00150', 'type' => 'select'], ['label' => 'Cliente', 'value' => 'Comercializadora Andina SAS'], ['label' => 'Motivo', 'value' => 'Corrección de valor', 'type' => 'select']]), self::section('Detalle', [['label' => 'Justificación', 'value' => 'Ajuste parcial del servicio por acuerdo comercial.', 'type' => 'textarea']])];
    }

    private static function creditNoteReviewSections(): array
    {
        return [self::section('Revisión final', [['label' => 'Factura origen', 'value' => 'FV-00150'], ['label' => 'Valor a descontar', 'value' => '$238.000'], ['label' => 'Confirmación requerida', 'value' => 'Pendiente de usuario']])];
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
