<x-filament-panels::page>
    <div class="grid gap-6">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:1rem;">
            <x-tbe::stat-card :label="__('tbe-user-wallet::web.balance')" :value="$this->balance()" icon="heroicon-o-wallet" />
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ __('tbe-user-wallet::web.top_ups') }}</x-slot>
            @forelse ($this->topUps() as $order)
                <x-tbe::row>
                    <span>#{{ $order->id }} · {{ $this->money($order->amount) }}</span>
                    <x-filament::badge :color="$this->statusColor($order->invoice?->status)">{{ $this->statusLabel($order->invoice?->status) }}</x-filament::badge>
                    <x-tbe::when :at="$order->created_at" />
                </x-tbe::row>
            @empty
                <x-tbe::empty>{{ __('tbe-user-wallet::web.no_top_ups') }}</x-tbe::empty>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('tbe-user-wallet::web.payments') }}</x-slot>
            @forelse ($this->payments() as $payment)
                <x-tbe::row>
                    <span>{{ $this->money($payment->amount) }}</span>
                    <x-filament::badge :color="$this->statusColor($payment->status)">{{ $this->statusLabel($payment->status) }}</x-filament::badge>
                    <x-tbe::when :at="$payment->created_at" />
                </x-tbe::row>
            @empty
                <x-tbe::empty>{{ __('tbe-user-wallet::web.no_payments') }}</x-tbe::empty>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
