<section class="w-full max-w-4xl">
    <div class="flex items-start justify-between pb-6">
        <div>
            <flux:text class="font-mono text-sm">{{ $folio->reference }}</flux:text>
            <flux:heading size="xl">{{ $folio->guest?->fullName() ?? __('Walk-in') }}</flux:heading>
            @if ($folio->stay?->reservation)
                <flux:text class="mt-1">
                    {{ __('For reservation') }}
                    <flux:link :href="route('reservations.show', $folio->stay->reservation)" wire:navigate>
                        {{ $folio->stay->reservation->confirmation_no }}
                    </flux:link>
                </flux:text>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @php
                $color = match ($folio->status) {
                    'open' => 'blue', 'closed' => 'orange', 'settled' => 'green', 'void' => 'zinc',
                    default => 'zinc',
                };
            @endphp
            <flux:badge :color="$color" size="lg">{{ ucfirst($folio->status) }}</flux:badge>

            <flux:button variant="ghost" icon="printer" :href="route('folios.print', $folio)" target="_blank">
                {{ __('Print bill') }}
            </flux:button>

            <flux:button variant="ghost" icon="envelope" wire:click="openSendEmail">
                {{ __('Email bill') }}
            </flux:button>

            <flux:button variant="ghost" icon="chat-bubble-left-right" wire:click="openSendSms">
                {{ __('SMS bill') }}
            </flux:button>

            @if (in_array($folio->status, ['open', 'closed'], true))
                <flux:button variant="primary" icon="banknotes" wire:click="openPayment">
                    {{ __('Record payment') }}
                </flux:button>
            @endif

            @if ($this->canRefund())
                <flux:button variant="primary" icon="arrow-uturn-left" wire:click="openRefund">
                    {{ __('Refund credit') }}
                </flux:button>
            @endif
        </div>
    </div>

    <flux:modal name="refund-credit" class="md:w-[480px]">
        <form wire:submit="refund" class="space-y-6">
            <flux:heading size="lg">{{ __('Refund guest credit') }}</flux:heading>
            <flux:text>
                {{ __('The guest has paid :amount more than they owe.', ['amount' => $this->creditBalance->format()]) }}
            </flux:text>

            <flux:select wire:model="refund_method" :label="__('Refunded by')" required>
                <flux:select.option value="">{{ __('Choose method…') }}</flux:select.option>
                @foreach ($this->refundMethods as $method)
                    <flux:select.option :value="$method->code">{{ $method->label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.number="refund_amount" type="number" min="1"
                :label="__('Amount (:currency)', ['currency' => $folio->currency])" required />

            <flux:input wire:model="refund_reference" :label="__('Reference (optional)')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record refund') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="send-bill-email" class="md:w-[480px]">
        <form wire:submit="sendByEmail" class="space-y-6">
            <flux:heading size="lg">{{ __('Email bill to guest') }}</flux:heading>
            <flux:text>{{ __('Sends the bill as an HTML email. Reference:') }} <span class="font-mono">{{ $folio->reference }}</span></flux:text>
            <flux:input wire:model="recipient_email" type="email" :label="__('Recipient email')" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send email') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="send-bill-sms" class="md:w-[480px]">
        <form wire:submit="sendBySms" class="space-y-6">
            <flux:heading size="lg">{{ __('SMS bill to guest') }}</flux:heading>
            <flux:text>{{ __('Sends a short SMS summary via Beem Africa. Reference:') }} <span class="font-mono">{{ $folio->reference }}</span></flux:text>
            <flux:input wire:model="recipient_phone" :label="__('Recipient phone')" placeholder="0712345678" required />
            <flux:text size="sm" class="text-zinc-500">
                {{ __('Local numbers starting with 0 are converted to +255. Otherwise include country code (e.g. 255712345678).') }}
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send SMS') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($this->fiscalReceipt)
        <flux:callout variant="success" icon="receipt-percent" class="mb-6">
            <flux:callout.heading>{{ __('Fiscal receipt issued') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Receipt no.') }} <span class="font-mono">{{ $this->fiscalReceipt->receipt_no }}</span>
                @if ($this->fiscalReceipt->tra_verification_code)
                    · {{ __('TRA verification') }}: <span class="font-mono">{{ $this->fiscalReceipt->tra_verification_code }}</span>
                @endif
                @if ($this->fiscalReceipt->provider === 'internal')
                    <flux:text class="text-xs">
                        {{ __('Internal receipt (TRA VFD adapter not yet enabled).') }}
                    </flux:text>
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="mb-6">
        <flux:heading size="lg">{{ __('Charges') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Qty') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($folio->charges as $charge)
                    <flux:table.row :key="$charge->id">
                        <flux:table.cell variant="strong">{{ $charge->description }}</flux:table.cell>
                        <flux:table.cell>{{ $charge->quantity }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $charge->unit_price->format() }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $charge->total->format() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No charges yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="mb-6">
        <flux:heading size="lg">{{ __('Payments') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Amount') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($folio->payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell variant="strong">
                            @if ($payment->isRefund())
                                <flux:badge color="red" size="sm">{{ __('Refund') }}</flux:badge>
                            @endif
                            {{ str_replace('_', ' ', ucfirst($payment->method_code)) }}
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $payment->reference ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$payment->status === 'confirmed' ? 'green' : 'yellow'" size="sm">
                                {{ ucfirst($payment->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $payment->amount->format() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No payments yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <div class="space-y-2">
            <div class="flex justify-between text-sm">
                <span>{{ __('Subtotal (before VAT)') }}</span>
                <span class="font-mono">{{ $folio->subtotal->format() }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>{{ \App\Support\Vat::current()->label() }}</span>
                <span class="font-mono">{{ $folio->tax_total->format() }}</span>
            </div>
            <flux:separator />
            <div class="flex justify-between">
                <flux:heading size="sm">{{ __('Total') }}</flux:heading>
                <span class="font-mono font-semibold">{{ $folio->total_amount->format() }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>{{ __('Paid') }}</span>
                <span class="font-mono">{{ $folio->paid_total->format() }}</span>
            </div>
            <flux:separator />
            @if ($this->creditBalance->isPositive())
                <div class="flex justify-between text-lg">
                    <flux:heading size="sm">{{ __('Credit due to guest') }}</flux:heading>
                    <span class="font-mono font-semibold text-red-600 dark:text-red-400">
                        {{ $this->creditBalance->format() }}
                    </span>
                </div>
            @else
                <div class="flex justify-between text-lg">
                    <flux:heading size="sm">{{ __('Balance due') }}</flux:heading>
                    <span class="font-mono font-semibold">
                        {{ $folio->total_amount->subtract($folio->paid_total)->format() }}
                    </span>
                </div>
            @endif
        </div>
    </flux:card>

    @if (in_array($folio->status, ['open', 'closed'], true))
        <livewire:folios.record-payment-form :folio="$folio" :key="'pay-'.$folio->id.'-'.$folio->paid_total->amount" />
    @endif
</section>
