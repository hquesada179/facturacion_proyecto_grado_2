@props(['items' => [], 'userId' => null])

{{--
    UI/UX 2.0-C — bell + notification dropdown. Items are built server-side
    by NotificationCenterService from existing audit trails (invoice_events,
    assistant_action_events); nothing here invents data. Read/unread state
    is intentionally NOT persisted in the database (see the phase report):
    it's tracked client-side in localStorage (notifications.js) purely as a
    visual aid, keyed per user so a shared browser never leaks another
    account's "seen" state.
--}}
<div class="relative" data-notification-center data-user-id="{{ $userId }}">
    <button
        type="button"
        class="icon-btn p-2 rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-container transition-all relative"
        data-notification-toggle
        aria-haspopup="true"
        aria-expanded="false"
        aria-label="Notificaciones"
    >
        <span class="material-symbols-outlined">notifications</span>
        <span
            class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-error text-on-error font-label-sm text-[10px] leading-[18px] text-center"
            data-notification-badge
        >0</span>
    </button>

    <div
        class="hidden absolute right-0 mt-sm w-[88vw] max-w-sm sm:w-96 sm:max-w-none surface-card shadow-xl z-50 overflow-hidden"
        data-notification-panel
        role="menu"
        aria-label="Centro de notificaciones"
    >
        <div class="flex items-center justify-between px-md py-sm border-b border-outline-variant/40">
            <p class="font-label-md text-label-md font-semibold text-on-surface">Notificaciones</p>
            @if (! empty($items))
                <button
                    type="button"
                    class="font-label-sm text-label-sm text-primary hover:underline"
                    data-notification-mark-read
                >
                    Marcar todo como leído
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-outline-variant/30" data-notification-list>
            @forelse ($items as $item)
                @php
                    $tone = match ($item['tone']) {
                        'success' => ['bg-success-soft', 'text-success-soft-foreground'],
                        'warning' => ['bg-warning-soft', 'text-warning-soft-foreground'],
                        'error' => ['bg-error-soft', 'text-error-soft-foreground'],
                        default => ['bg-neutral-soft', 'text-neutral-soft-foreground'],
                    };
                @endphp
                <a
                    href="{{ $item['url'] }}"
                    class="flex items-start gap-sm px-md py-sm hover:bg-surface-container-low transition-colors"
                    data-notification-item
                    data-timestamp="{{ $item['timestamp']->toIso8601String() }}"
                    role="menuitem"
                >
                    <span class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center {{ $tone[0] }} {{ $tone[1] }}">
                        <span class="material-symbols-outlined text-[18px]">{{ $item['icon'] }}</span>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-label-md text-label-md font-semibold text-on-surface">{{ $item['title'] }}</span>
                        <span class="block font-body-sm text-body-sm text-on-surface-variant line-clamp-2">{{ $item['description'] }}</span>
                        <span class="block font-label-sm text-label-sm text-outline mt-xs">{{ $item['timestamp']->diffForHumans() }}</span>
                    </span>
                    <span class="w-2 h-2 rounded-full bg-primary mt-1.5 shrink-0" data-notification-unread-dot hidden aria-hidden="true"></span>
                </a>
            @empty
                <div class="flex flex-col items-center gap-sm py-xl px-md text-center" data-notification-empty>
                    <span class="w-10 h-10 rounded-full bg-neutral-soft text-neutral-soft-foreground flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">notifications_off</span>
                    </span>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">No tienes notificaciones por ahora.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
