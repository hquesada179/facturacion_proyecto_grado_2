@props(['checklist'])

{{--
    "Primeros pasos" — every item's `done` flag comes from real data
    (OnboardingService::checklistFor(), see its own doc comment), never
    a stored flag. "Ocultar" is a pure client-side preference
    (resources/js/checklist.js, localStorage key
    fiscora.checklist.hidden.<companyId>) — the real completion state
    behind it is never touched by hiding the card, so re-showing it via
    "Ver tutorial"/Centro de ayuda always reflects the current truth.
--}}
<div
    class="surface-card onboarding-checklist p-lg mb-lg"
    id="onboarding-checklist"
    data-onboarding-checklist
    data-company-id="{{ auth()->user()->company_id }}"
>
    <div class="onboarding-checklist-top">
        <div class="onboarding-checklist-title">
            <span class="material-symbols-outlined">flag</span>
            <h3>Primeros pasos</h3>
        </div>
        <span class="onboarding-checklist-count">{{ $checklist['doneCount'] }} de {{ $checklist['total'] }} completados</span>
        <div class="onboarding-checklist-actions">
            <button type="button" class="onboarding-checklist-link" data-checklist-tutorial>Ver tutorial</button>
            <button type="button" class="onboarding-checklist-link" data-checklist-hide aria-label="Ocultar primeros pasos">Ocultar</button>
        </div>
    </div>

    <div class="onboarding-checklist-bar">
        <span style="width: {{ $checklist['percent'] }}%"></span>
    </div>

    <div class="onboarding-checklist-items">
        @foreach ($checklist['items'] as $item)
            <div class="onboarding-checklist-item {{ $item['done'] ? 'done' : '' }}">
                <span class="chk">
                    @if ($item['done'])
                        <span class="material-symbols-outlined">check</span>
                    @endif
                </span>
                {{ $item['label'] }}
            </div>
        @endforeach
    </div>

    <div class="onboarding-checklist-banner {{ $checklist['percent'] >= 100 ? 'show' : '' }}">
        <span class="material-symbols-outlined">check_circle</span>
        ¡Configuración completada!
    </div>
</div>
