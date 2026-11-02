{{--
    Same popover pattern as notification-center.blade.php (button +
    absolutely-positioned panel toggled by a data attribute, wired by
    resources/js/help-center.js). Every item either starts the real
    product tour, restores the real checklist, or links to an existing
    route — nothing here invents a new page or a parallel assistant.
--}}
<div class="relative" data-help-center>
    <button
        type="button"
        class="icon-btn p-2 rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-container transition-all"
        data-help-toggle
        aria-haspopup="true"
        aria-expanded="false"
        aria-label="Centro de ayuda"
    >
        <span class="material-symbols-outlined">help_outline</span>
    </button>

    <div
        class="help-center-panel"
        data-help-panel
        role="menu"
        aria-label="Centro de ayuda"
    >
        <div class="help-center-panel-head">Centro de ayuda</div>

        <button type="button" class="help-center-item" data-help-action="start-tour" role="menuitem">
            <span class="material-symbols-outlined">play_circle</span>
            Ver tutorial de Fiscora
            <span class="material-symbols-outlined chev">chevron_right</span>
        </button>

        <button type="button" class="help-center-item" data-help-action="show-checklist" role="menuitem">
            <span class="material-symbols-outlined">flag</span>
            Primeros pasos
            <span class="material-symbols-outlined chev">chevron_right</span>
        </button>

        @can('manage-invoicing')
            <a href="{{ route('invoices.create.customer') }}" class="help-center-item" role="menuitem">
                <span class="material-symbols-outlined">description</span>
                Cómo crear una factura
                <span class="material-symbols-outlined chev">chevron_right</span>
            </a>
        @endcan

        <a href="{{ route('credit-notes.index') }}" class="help-center-item" role="menuitem">
            <span class="material-symbols-outlined">assignment_return</span>
            Cómo crear una nota crédito
            <span class="material-symbols-outlined chev">chevron_right</span>
        </a>

        <a href="{{ route('invoices.index') }}" class="help-center-item" role="menuitem">
            <span class="material-symbols-outlined">error</span>
            Cómo corregir una validación
            <span class="material-symbols-outlined chev">chevron_right</span>
        </a>

        <button type="button" class="help-center-item" data-help-action="focus-assistant" role="menuitem">
            <span class="material-symbols-outlined">auto_awesome</span>
            Cómo usar el Asistente IA
            <span class="material-symbols-outlined chev">chevron_right</span>
        </button>

        <button type="button" class="help-center-item" data-help-action="toggle-about" role="menuitem" aria-expanded="false">
            <span class="material-symbols-outlined">info</span>
            Acerca del prototipo
            <span class="material-symbols-outlined chev">chevron_right</span>
        </button>
        <p class="help-center-about" data-help-about>
            Fiscora es un prototipo académico. La facturación, validación y emisión son simuladas y no tienen validez tributaria real.
        </p>
    </div>
</div>
