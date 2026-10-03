<x-layouts.app title="Asistente IA" active="assistant" :assistant="false" wide>
    <x-page-header
        eyebrow="Asistente"
        title="Asistente IA contextual"
        description="Consulta información real del sistema. Las acciones críticas requieren confirmación humana."
    />

    <div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-lg min-h-[calc(100vh-12rem)]">
        <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-sm">Alcance seguro</h3>
            <div class="space-y-sm font-body-sm text-body-sm text-on-surface-variant">
                <p>El asistente usa herramientas internas limitadas y respeta empresa, políticas y gates.</p>
                <p>No ejecuta SQL, no confirma emisiones ni cambia datos desde una respuesta textual.</p>
                <p>Si no hay proveedor externo configurado, funciona en modo local determinista.</p>
            </div>
        </section>

        <x-assistant-panel mode="full" />
    </div>
</x-layouts.app>
