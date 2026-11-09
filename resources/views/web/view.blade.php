<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament::section>
            <dl>
                <dt style="font-size:.875rem;opacity:.7;">{{ __('tbe-user-wallet::web.balance') }}</dt>
                <dd style="font-size:1.75rem;font-weight:600;">{{ $this->balance() }}</dd>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('tbe-user-wallet::web.top_ups') }}</x-slot>
            @forelse ($this->topUps() as $order)
                <div style="display:flex;gap:1rem;justify-content:space-between;padding:.375rem 0;border-bottom:1px solid rgb(127 127 127 / .15);">
                    <span>#{{ $order->id }} · {{ $this->money($order->amount) }}</span>
                    <span>{{ $order->invoice?->status ?? '—' }}</span>
                    <span style="opacity:.7;">{{ $order->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p style="opacity:.7;">{{ __('tbe-user-wallet::web.no_top_ups') }}</p>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('tbe-user-wallet::web.payments') }}</x-slot>
            @forelse ($this->payments() as $payment)
                <div style="display:flex;gap:1rem;justify-content:space-between;padding:.375rem 0;border-bottom:1px solid rgb(127 127 127 / .15);">
                    <span>{{ $this->money($payment->amount) }}</span>
                    <span>{{ $payment->status ?? '—' }}</span>
                    <span style="opacity:.7;">{{ $payment->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p style="opacity:.7;">{{ __('tbe-user-wallet::web.no_payments') }}</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
