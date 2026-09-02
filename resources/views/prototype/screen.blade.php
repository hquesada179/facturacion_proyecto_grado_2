<x-layouts.app
    :title="$page['title']"
    :active="$page['active']"
    :assistant="$page['assistant'] ?? true"
    :wide="$page['wide'] ?? false"
>
    @if ($page['template'] !== 'assistant')
        <x-page-header :eyebrow="$page['eyebrow'] ?? null" :title="$page['title']" :description="$page['description'] ?? null">
            <x-slot:actions>
                @if (! empty($page['primaryAction']))
                    <a href="{{ route($page['primaryAction']['route']) }}" class="bg-primary text-on-primary rounded-lg px-md py-sm flex items-center gap-sm font-label-md text-label-md shadow-hover">
                        <span class="material-symbols-outlined text-[18px]">{{ $page['primaryAction']['icon'] }}</span>
                        {{ $page['primaryAction']['label'] }}
                    </a>
                @endif
            </x-slot:actions>
        </x-page-header>
    @endif

    @if ($page['template'] === 'dashboard')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-md mb-xl">
            @foreach ($page['metrics'] as $metric)
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
            @endforeach
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft flex flex-wrap gap-md mb-lg border border-outline-variant/40">
            @foreach ($page['quickActions'] as $action)
                <a href="{{ route($action['route']) }}" class="flex-1 min-w-[150px] {{ ! empty($action['primary']) ? 'bg-primary text-on-primary' : 'bg-surface-container-lowest border border-outline-variant text-on-surface hover:border-primary hover:text-primary' }} font-label-md text-label-md py-sm px-md rounded-lg flex flex-col items-center justify-center gap-xs shadow-hover h-24 transition-colors">
                    <span class="material-symbols-outlined text-2xl">{{ $action['icon'] }}</span>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>

        <x-ui.data-table :table="$page['table']" />
    @elseif ($page['template'] === 'list')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
            @foreach ($page['metrics'] as $metric)
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
            @endforeach
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg flex flex-col md:flex-row gap-md md:items-center md:justify-between">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
                <input class="w-full pl-10 pr-4 py-2 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm focus:border-primary focus:ring focus:ring-primary/10" placeholder="Buscar en {{ strtolower($page['title']) }}" type="search">
            </div>
            <div class="flex gap-sm">
                <button class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md" type="button">Filtrar</button>
                <button class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md" type="button">Exportar</button>
            </div>
        </div>

        <x-ui.data-table :table="$page['table']" />
    @elseif ($page['template'] === 'form')
        @if (! empty($page['step']))
            <x-invoice-stepper :current="$page['step']" />
        @endif

        <form class="space-y-lg" data-demo-submit="prototype-save-alert">
            <div id="prototype-save-alert" class="hidden bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm">
                Los datos quedaron listos en modo prototipo. La persistencia funcional se implementará en la siguiente fase.
            </div>

            @foreach ($page['sections'] as $section)
                <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
                    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">{{ $section['title'] }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                        @foreach ($section['fields'] as $field)
                            <label class="{{ ($field['type'] ?? null) === 'textarea' ? 'md:col-span-2' : '' }}">
                                <span class="block font-label-md text-label-md text-on-surface mb-xs">{{ $field['label'] }}</span>
                                @if (($field['type'] ?? null) === 'textarea')
                                    <textarea class="w-full min-h-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">{{ $field['value'] }}</textarea>
                                @elseif (($field['type'] ?? null) === 'select')
                                    <select class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                                        <option selected>{{ $field['value'] }}</option>
                                    </select>
                                @else
                                    <div class="relative">
                                        @if (! empty($field['icon']))
                                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">{{ $field['icon'] }}</span>
                                        @endif
                                        <input class="w-full {{ ! empty($field['icon']) ? 'pl-10' : 'pl-md' }} pr-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" value="{{ $field['value'] }}" type="{{ $field['type'] ?? 'text' }}">
                                    </div>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if (! empty($page['table']))
                <x-ui.data-table :table="$page['table']" />
            @endif

            <div class="flex flex-col sm:flex-row justify-end gap-sm">
                <button class="px-md py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md" type="button">Cancelar</button>
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center justify-center gap-sm" type="submit">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Guardar
                </button>
            </div>
        </form>
    @elseif ($page['template'] === 'detail')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg">
            @foreach ($page['cards'] as $card)
                <div class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">{{ $card['label'] }}</p>
                    <p class="font-title-lg text-title-lg text-on-surface">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-lg">
            <x-ui.data-table :table="$page['table']" />

            <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40">
                <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Trazabilidad</h3>
                <div class="space-y-md">
                    @foreach ($page['timeline'] as $event)
                        <div class="flex gap-sm">
                            <div class="w-9 h-9 rounded-full bg-primary-fixed text-primary flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">history</span>
                            </div>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface">{{ $event['title'] }} <span class="text-on-surface-variant">{{ $event['time'] }}</span></p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $event['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @elseif ($page['template'] === 'report')
        <div class="bg-surface-container-lowest rounded-xl p-md shadow-soft border border-outline-variant/40 mb-lg flex flex-col md:flex-row gap-md md:items-center">
            <select class="rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                <option>Últimos 30 días</option>
            </select>
            <select class="rounded-lg border border-outline-variant bg-surface px-md py-sm font-body-sm text-body-sm">
                <option>Todos los módulos</option>
                <option>Asistente IA</option>
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-md mb-lg">
            @foreach ($page['metrics'] as $metric)
                <x-ui.kpi-card :label="$metric['label']" :value="$metric['value']" :icon="$metric['icon']" :tone="$metric['tone']" />
            @endforeach
        </div>

        <section class="bg-surface-container-lowest rounded-xl p-lg shadow-soft border border-outline-variant/40 mb-lg">
            <h3 class="font-title-lg text-title-lg text-on-surface mb-md">Facturas generadas por período</h3>
            <div class="h-64 flex items-end gap-md border-l border-b border-outline-variant/50 px-md py-md">
                @foreach ([40, 65, 52, 78, 58, 86, 72] as $height)
                    <div class="flex-1 bg-primary rounded-t-lg min-w-8" style="height: {{ $height }}%"></div>
                @endforeach
            </div>
        </section>

        <x-ui.data-table :table="$page['table']" />
    @elseif ($page['template'] === 'settings')
        <div class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 mb-lg">
            <div class="flex flex-wrap p-sm gap-sm">
                @foreach (\App\Support\PrototypeScreens::settingsTabs() as $tab)
                    @php($isActive = request()->routeIs($tab['route']))
                    <a href="{{ route($tab['route']) }}" class="px-md py-sm rounded-lg font-label-md text-label-md {{ $isActive ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' }}">{{ $tab['label'] }}</a>
                @endforeach
            </div>
        </div>

        <form class="space-y-lg" data-demo-submit="settings-save-alert">
            <div id="settings-save-alert" class="hidden bg-primary-fixed border border-primary-fixed-dim text-on-primary-fixed rounded-lg p-md font-body-sm text-body-sm">
                Configuración guardada en modo visual. La persistencia real queda pendiente.
            </div>
            @foreach ($page['sections'] as $section)
                <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-lg">
                    <h3 class="font-title-lg text-title-lg text-on-surface mb-md">{{ $section['title'] }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                        @foreach ($section['fields'] as $field)
                            <label>
                                <span class="block font-label-md text-label-md text-on-surface mb-xs">{{ $field['label'] }}</span>
                                @if (($field['type'] ?? null) === 'select')
                                    <select class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md">
                                        <option selected>{{ $field['value'] }}</option>
                                    </select>
                                @else
                                    <input class="w-full px-md py-sm rounded-lg border border-outline-variant bg-surface-container-lowest focus:border-primary focus:ring focus:ring-primary/10 font-body-md text-body-md" value="{{ $field['value'] }}" type="{{ $field['type'] ?? 'text' }}">
                                @endif
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <div class="flex justify-end">
                <button class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover flex items-center gap-sm" type="submit">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Guardar cambios
                </button>
            </div>
        </form>
    @elseif ($page['template'] === 'final')
        <section class="bg-surface-container-lowest rounded-xl shadow-soft border border-outline-variant/40 p-xl text-center">
            <div class="w-16 h-16 rounded-full bg-[#def7ec] text-[#03543f] flex items-center justify-center mx-auto mb-md">
                <span class="material-symbols-outlined text-[36px]" data-fill="true">check_circle</span>
            </div>
            <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">Factura finalizada con éxito</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-lg">El documento quedó registrado en modo simulado y disponible para consulta.</p>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-lg text-left">
                @foreach ($page['summary'] as $item)
                    <div class="bg-surface rounded-lg p-md border border-outline-variant/40">
                        <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">{{ $item['label'] }}</p>
                        <p class="font-title-lg text-title-lg text-on-surface">{{ $item['value'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-col sm:flex-row gap-sm justify-center">
                <a href="{{ route('invoices.show') }}" class="px-lg py-sm rounded-lg bg-primary text-on-primary font-label-md text-label-md shadow-hover">Ver factura</a>
                <a href="{{ route('invoices.index') }}" class="px-lg py-sm rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary font-label-md text-label-md">Volver al listado</a>
            </div>
        </section>
    @elseif ($page['template'] === 'assistant')
        <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] min-h-[calc(100vh-9rem)] bg-surface-container-lowest rounded-xl border border-outline-variant/40 shadow-soft overflow-hidden">
            <aside class="border-r border-outline-variant/40 bg-surface p-lg">
                <div class="flex items-center gap-sm mb-lg">
                    <span class="material-symbols-outlined ai-gradient-text text-[32px]">smart_toy</span>
                    <div>
                        <h2 class="font-title-lg text-title-lg text-on-surface">Asistente IA</h2>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">Conversaciones</p>
                    </div>
                </div>
                <div class="space-y-sm">
                    @foreach (['Validación de factura', 'Corrección de cliente', 'Nota crédito', 'Trazabilidad'] as $conversation)
                        <button class="w-full text-left rounded-lg p-md bg-surface-container-lowest border border-outline-variant/40 hover:border-primary hover:bg-surface-container-low font-body-sm text-body-sm" type="button">{{ $conversation }}</button>
                    @endforeach
                </div>
            </aside>

            <section class="flex flex-col">
                <div class="p-lg border-b border-outline-variant/40">
                    <x-page-header :eyebrow="$page['eyebrow']" :title="$page['title']" :description="$page['description']" />
                </div>
                <div class="flex-1 p-lg space-y-md bg-surface-container-low">
                    <div class="max-w-2xl bg-surface-container-lowest rounded-xl p-md shadow-soft">
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Hola, soy el asistente contextual del prototipo. Puedo orientar validaciones, explicar errores y resumir trazabilidad sin ejecutar acciones críticas automáticamente.</p>
                    </div>
                    <div class="max-w-2xl ml-auto bg-primary text-on-primary rounded-xl p-md shadow-soft">
                        <p class="font-body-sm text-body-sm">Revisa la factura FV-00156 y dime si falta algo antes de finalizar.</p>
                    </div>
                    <div class="max-w-2xl bg-surface-container-lowest rounded-xl p-md shadow-soft">
                        <p class="font-body-sm text-body-sm text-on-surface-variant">La factura tiene cliente, impuesto y totales consistentes. Para finalizar, confirma manualmente la acción desde el flujo de validación.</p>
                    </div>
                </div>
                <form class="p-lg border-t border-outline-variant/40 bg-surface-container-lowest" data-assistant-demo>
                    <div class="relative">
                        <input class="w-full rounded-full border border-outline-variant bg-surface pl-md pr-12 py-md focus:border-primary focus:ring focus:ring-primary/10" placeholder="Escribe una consulta contextual..." type="text">
                        <button class="absolute right-2 top-1/2 -translate-y-1/2 ai-gradient text-on-primary rounded-full p-sm" type="submit" aria-label="Enviar">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</x-layouts.app>
