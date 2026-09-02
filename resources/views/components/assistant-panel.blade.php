<div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-surface-variant h-full min-h-[520px] flex flex-col relative overflow-hidden">
    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full"></div>

    <div class="relative flex items-center gap-sm mb-md">
        <span class="material-symbols-outlined ai-gradient-text text-3xl">auto_awesome</span>
        <div>
            <h3 class="font-title-lg text-title-lg text-on-surface">Asistente IA</h3>
            <p class="font-label-sm text-label-sm text-on-surface-variant">Panel contextual</p>
        </div>
    </div>

    <div class="relative bg-surface-container-low rounded-lg p-md mb-md text-on-surface-variant font-body-sm text-body-sm">
        Puedo ayudarte según la pantalla y el documento que estés consultando. Las acciones críticas requieren confirmación del usuario.
    </div>

    <div class="relative flex flex-col gap-sm flex-1">
        @foreach ([
            ['icon' => 'edit_document', 'label' => 'Crear una factura'],
            ['icon' => 'healing', 'label' => 'Corregir un error'],
            ['icon' => 'assignment_return', 'label' => 'Generar nota crédito'],
            ['icon' => 'manage_search', 'label' => 'Consultar trazabilidad'],
        ] as $action)
            <button class="flex items-center justify-between px-md py-sm rounded-lg border border-outline-variant text-on-surface hover:border-primary hover:bg-surface-container-low transition-all font-label-md text-label-md group text-left" type="button">
                <span class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-outline group-hover:text-primary text-lg">{{ $action['icon'] }}</span>
                    {{ $action['label'] }}
                </span>
                <span class="material-symbols-outlined text-outline group-hover:text-primary text-sm">arrow_forward</span>
            </button>
        @endforeach
    </div>

    <form class="relative mt-md pt-md border-t border-surface-variant" data-assistant-demo>
        <label class="sr-only" for="assistant-question">Pregunta al asistente</label>
        <div class="relative w-full">
            <input id="assistant-question" class="w-full pl-4 pr-10 py-2 bg-surface border border-outline-variant rounded-full font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10 transition-all" placeholder="Pregúntale a la IA..." type="text">
            <button class="absolute right-2 top-1/2 -translate-y-1/2 text-primary p-1 rounded-full hover:bg-surface-container-highest transition-colors" type="submit" aria-label="Enviar">
                <span class="material-symbols-outlined text-xl">send</span>
            </button>
        </div>
    </form>
</div>
