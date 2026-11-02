@props([
    'mode' => 'sidebar',
    'screen' => null,
    'resourceType' => null,
    'resourceId' => null,
])

@php
    $resolvedScreen = $screen ?? 'unknown';

    $quickActions = match (true) {
        $resourceType === 'invoice' && str_contains($resolvedScreen, 'validation') => [
            ['icon' => 'fact_check', 'label' => 'Explicar errores', 'type' => 'message', 'value' => '¿Por qué no puedo emitir esta factura?'],
            ['icon' => 'percent', 'label' => 'Revisar impuestos', 'type' => 'message', 'value' => '¿Cuánto IVA tiene esta factura y cómo se calculó?'],
            ['icon' => 'summarize', 'label' => 'Explicar total', 'type' => 'message', 'value' => 'Explícame el total de esta factura'],
            ['icon' => 'arrow_upward', 'label' => 'Volver al campo problemático', 'type' => 'anchor', 'value' => '#validation-summary'],
        ],
        $resourceType === 'invoice' => [
            ['icon' => 'fact_check', 'label' => 'Explicar factura', 'type' => 'message', 'value' => 'Explícame esta factura'],
            ['icon' => 'manage_search', 'label' => 'Ver trazabilidad', 'type' => 'message', 'value' => '¿Qué pasó con esta factura?'],
            ['icon' => 'percent', 'label' => 'Explicar impuestos', 'type' => 'message', 'value' => '¿Cuánto IVA tiene esta factura y cómo se calculó?'],
            ['icon' => 'assignment_return', 'label' => 'Notas crédito relacionadas', 'type' => 'message', 'value' => '¿Qué nota crédito tiene esta factura?'],
        ],
        $resourceType === 'credit_note' => [
            ['icon' => 'assignment_return', 'label' => 'Explicar nota crédito', 'type' => 'message', 'value' => '¿Por qué esta factura está parcialmente acreditada?'],
            ['icon' => 'manage_search', 'label' => 'Consultar trazabilidad', 'type' => 'message', 'value' => '¿Qué pasó con esta nota crédito?'],
        ],
        $resourceType === 'customer' => [
            ['icon' => 'receipt_long', 'label' => '¿Qué documentos tiene?', 'type' => 'message', 'value' => '¿Qué documentos tiene este cliente?'],
            ['icon' => 'edit_document', 'label' => 'Crear una factura', 'type' => 'message', 'value' => '¿Cómo creo una factura nueva para este cliente?'],
        ],
        $resourceType === 'product' => [
            ['icon' => 'inventory_2', 'label' => 'Impuestos del producto', 'type' => 'message', 'value' => '¿Qué impuestos tiene este producto?'],
        ],
        str_contains($resolvedScreen, 'dashboard') => [
            ['icon' => 'today', 'label' => 'Resumen de hoy', 'type' => 'message', 'value' => '¿Cuántas facturas tengo hoy?'],
            ['icon' => 'pending_actions', 'label' => 'Borradores pendientes', 'type' => 'message', 'value' => 'Muéstrame mis borradores pendientes'],
            ['icon' => 'error', 'label' => 'Errores recientes', 'type' => 'message', 'value' => '¿Qué errores se han presentado recientemente?'],
            ['icon' => 'payments', 'label' => 'Facturación del mes', 'type' => 'message', 'value' => '¿Cuánto he facturado este mes?'],
        ],
        str_contains($resolvedScreen, 'invoices') => [
            ['icon' => 'receipt_long', 'label' => 'Emitidas', 'type' => 'message', 'value' => 'Muéstrame las facturas emitidas'],
            ['icon' => 'block', 'label' => 'Anuladas', 'type' => 'message', 'value' => 'Muéstrame las facturas anuladas'],
            ['icon' => 'note_add', 'label' => 'Borradores', 'type' => 'message', 'value' => 'Muéstrame los borradores'],
            ['icon' => 'search', 'label' => 'Buscar factura', 'type' => 'focus', 'value' => 'Busca la factura '],
        ],
        str_contains($resolvedScreen, 'customers') => [
            ['icon' => 'search', 'label' => 'Buscar cliente', 'type' => 'focus', 'value' => 'Busca el cliente '],
            ['icon' => 'trending_up', 'label' => 'Clientes con más facturación', 'type' => 'message', 'value' => '¿Qué cliente tiene más facturación?'],
            ['icon' => 'group', 'label' => 'Clientes recientes', 'type' => 'message', 'value' => 'Muéstrame los clientes'],
        ],
        str_contains($resolvedScreen, 'products') => [
            ['icon' => 'inventory_2', 'label' => 'Productos activos', 'type' => 'message', 'value' => 'Muéstrame los productos activos'],
            ['icon' => 'percent', 'label' => 'IVA 19%', 'type' => 'message', 'value' => 'Muéstrame productos con IVA 19%'],
            ['icon' => 'trending_up', 'label' => 'Más facturados', 'type' => 'message', 'value' => '¿Qué producto se ha facturado más?'],
        ],
        str_contains($resolvedScreen, 'reports') => [
            ['icon' => 'summarize', 'label' => 'Resumen del mes', 'type' => 'message', 'value' => '¿Cuánto he facturado este mes?'],
            ['icon' => 'percent', 'label' => 'IVA generado', 'type' => 'message', 'value' => '¿Cuánto IVA he generado este mes?'],
            ['icon' => 'account_balance', 'label' => 'Facturación neta', 'type' => 'message', 'value' => '¿Cuál es mi facturación neta después de notas crédito?'],
        ],
        default => [
            ['icon' => 'edit_document', 'label' => 'Crear una factura', 'type' => 'message', 'value' => '¿Cómo creo una factura nueva?'],
            ['icon' => 'healing', 'label' => 'Corregir un error', 'type' => 'message', 'value' => '¿Cómo corrijo un error de validación?'],
            ['icon' => 'assignment_return', 'label' => 'Generar nota crédito', 'type' => 'message', 'value' => '¿Cómo genero una nota crédito?'],
            ['icon' => 'manage_search', 'label' => 'Consultar trazabilidad', 'type' => 'message', 'value' => '¿Qué pasó con esta factura?'],
        ],
    };

    $panelId = 'assistant-panel-'.uniqid();
@endphp

<div
    id="{{ $panelId }}"
    data-assistant-panel
    data-screen="{{ $resolvedScreen }}"
    data-resource-type="{{ $resourceType ?? '' }}"
    data-resource-id="{{ $resourceId ?? '' }}"
    data-message-url="{{ route('assistant.message') }}"
    data-confirm-url="{{ route('assistant.confirm') }}"
    data-feedback-url-template="{{ route('assistant.feedback', ['assistantMessage' => '__ID__']) }}"
    class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-surface-variant {{ $mode === 'full' ? 'h-full min-h-[640px]' : 'min-h-[520px]' }} flex flex-col relative overflow-hidden"
>
    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full"></div>

    <div class="relative flex items-center gap-sm mb-md">
        <span class="material-symbols-outlined ai-gradient-text text-3xl">auto_awesome</span>
        <div class="flex-1 min-w-0">
            <h3 class="font-title-lg text-title-lg text-on-surface">Asistente IA</h3>
            <p class="font-label-sm text-label-sm text-on-surface-variant" data-assistant-status>Panel contextual</p>
        </div>
        @if ($mode !== 'full')
            <button
                type="button"
                class="p-xs rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-container-highest transition-colors"
                data-assistant-expand-toggle
                aria-label="Expandir asistente"
                title="Expandir asistente"
            >
                <span class="material-symbols-outlined text-lg">open_in_full</span>
            </button>
        @endif
        <button
            type="button"
            class="p-xs rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-container-highest transition-colors"
            data-assistant-minimize-toggle
            aria-label="Minimizar asistente"
            title="Minimizar asistente"
        >
            <span class="material-symbols-outlined text-lg">remove</span>
        </button>
    </div>

    <div data-assistant-body class="relative flex flex-col flex-1 min-h-0">
        <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs" data-assistant-quick-actions-label>
            Sugerencias rápidas
        </p>
        <div class="relative flex flex-col gap-sm mb-md" data-assistant-quick-actions>
            @foreach ($quickActions as $action)
                <button
                    type="button"
                    class="flex items-center justify-between px-md py-sm rounded-lg border border-outline-variant text-on-surface hover:border-primary hover:bg-surface-container-low transition-all font-label-md text-label-md group text-left"
                    data-assistant-quick-action
                    data-type="{{ $action['type'] }}"
                    data-value="{{ $action['value'] }}"
                >
                    <span class="flex items-center gap-sm">
                        <span class="material-symbols-outlined text-outline group-hover:text-primary text-lg">{{ $action['icon'] }}</span>
                        {{ $action['label'] }}
                    </span>
                    <span class="material-symbols-outlined text-outline group-hover:text-primary text-sm">arrow_forward</span>
                </button>
            @endforeach
        </div>

        <div
            class="relative flex-1 min-h-[180px] overflow-y-auto rounded-lg bg-surface-container-low p-md mb-sm space-y-md font-body-sm text-body-sm"
            data-assistant-messages
        >
            <div class="text-on-surface-variant">
                Puedo ayudarte según la pantalla y el documento que estés consultando. Las acciones críticas requieren confirmación del usuario.
            </div>
        </div>

        <div class="relative hidden items-center gap-sm text-on-surface-variant font-label-sm text-label-sm mb-sm" data-assistant-loading>
            <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
            Pensando...
        </div>

        <form class="relative pt-md border-t border-surface-variant" data-assistant-form>
            <label class="sr-only" for="{{ $panelId }}-input">Pregunta al asistente</label>
            <div class="relative w-full">
                <input
                    id="{{ $panelId }}-input"
                    class="w-full pl-4 pr-10 py-2 bg-surface border border-outline-variant rounded-full font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10 transition-all"
                    placeholder="Pregúntale a la IA..."
                    type="text"
                    autocomplete="off"
                    data-assistant-input
                >
                <button class="absolute right-2 top-1/2 -translate-y-1/2 text-primary p-1 rounded-full hover:bg-surface-container-highest transition-colors" type="submit" aria-label="Enviar">
                    <span class="material-symbols-outlined text-xl">send</span>
                </button>
            </div>
        </form>
    </div>
</div>
