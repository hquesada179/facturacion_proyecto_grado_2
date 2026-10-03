<x-layouts.app title="Perfil de usuario" active="settings" screen="profile.show">
    <x-page-header eyebrow="Perfil" title="Perfil de usuario" description="Datos reales de la cuenta autenticada." />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-md mb-xl">
        @foreach ([
            ['label' => 'Usuario', 'value' => $user->name],
            ['label' => 'Correo', 'value' => $user->email],
            ['label' => 'Rol', 'value' => $user->role->label()],
            ['label' => 'Empresa', 'value' => $user->company?->name ?? 'Sin empresa asignada'],
        ] as $card)
            <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">{{ $card['label'] }}</p>
                <p class="font-title-md text-title-md text-on-surface">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <x-ui.data-table :table="[
        'title' => 'Actividad reciente',
        'headers' => ['Fecha', 'Documento', 'Evento'],
        'hideViewAll' => true,
        'emptyMessage' => 'Todavía no tienes eventos de trazabilidad registrados.',
        'rows' => $recentActivity->map(fn ($event) => [
            'cells' => [
                $event->created_at->format('Y-m-d H:i'),
                $event->invoice->number ?? 'Borrador #'.$event->invoice_id,
                $event->description,
            ],
        ])->all(),
    ]" />
</x-layouts.app>
