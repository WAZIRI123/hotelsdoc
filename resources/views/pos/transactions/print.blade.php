<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Receipt') }} {{ $order->reference }} — {{ $hotel->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #111827;
            background: #F3F3F3;
            font-size: 13px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page {
            max-width: 380px;
            margin: 24px auto;
            background: #ffffff;
            padding: 28px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 6px;
        }

        .toolbar {
            max-width: 380px;
            margin: 16px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .toolbar button, .toolbar a {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .toolbar a.secondary { background: #52525b; }
        .toolbar button:hover, .toolbar a:hover { opacity: 0.9; }

        .brand {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 1px dashed #9ca3af;
        }
        .brand img {
            width: 72px;
            height: auto;
            background: #111827;
            padding: 6px;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .brand h1 {
            margin: 4px 0 2px;
            font-size: 18px;
            font-weight: 600;
        }
        .brand p { margin: 1px 0; color: #4b5563; font-size: 11px; }
        .brand .outlet {
            margin-top: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .doc-label {
            text-align: center;
            margin: 14px 0 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #111827;
        }
        .doc-label span {
            display: inline-block;
            background: #111827;
            color: #fff;
            padding: 4px 12px;
            border-radius: 3px;
        }

        .meta {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 4px 10px;
            font-size: 12px;
            margin-bottom: 12px;
        }
        .meta dt { color: #6b7280; }
        .meta dd { margin: 0; font-family: 'Courier New', monospace; font-weight: 600; text-align: right; }

        .divider {
            border-top: 1px dashed #9ca3af;
            margin: 10px 0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        table.items thead th {
            text-align: left;
            padding: 6px 0;
            border-bottom: 1px solid #111827;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            font-weight: 600;
        }
        table.items thead th.num { text-align: right; }
        table.items tbody td {
            padding: 6px 0;
            vertical-align: top;
            border-bottom: 1px dotted #e5e7eb;
        }
        table.items tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        table.items tbody td.desc { font-weight: 500; }
        table.items tbody td .unit { color: #9ca3af; font-size: 11px; }
        table.items tbody tr:last-child td { border-bottom: none; }
        table.items tbody tr.empty td {
            text-align: center;
            color: #9ca3af;
            padding: 12px 0;
        }

        .totals {
            margin-top: 8px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }
        .totals .row.sub { border-top: 1px solid #111827; margin-top: 4px; padding-top: 8px; }
        .totals .row .value { font-family: 'Courier New', monospace; }
        .totals .row.grand {
            margin-top: 6px;
            padding: 8px 10px;
            background: #111827;
            color: #fff;
            border-radius: 3px;
            font-size: 15px;
            font-weight: 700;
        }

        .payment {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 4px;
            background: #F3F3F3;
            font-size: 12px;
        }
        .payment .row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }
        .payment .label { color: #6b7280; }
        .payment .value { font-family: 'Courier New', monospace; font-weight: 600; }
        .payment.paid { background: #f0fdf4; color: #166534; border-left: 3px solid #16a34a; }
        .payment.due  { background: #fef2f2; color: #991b1b; border-left: 3px solid #dc2626; }

        .footer {
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px dashed #9ca3af;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }
        .footer .thanks {
            font-size: 13px;
            color: #111827;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .footer .ref { margin-top: 6px; font-family: 'Courier New', monospace; }
        .footer .note { margin-top: 6px; white-space: pre-line; color: #374151; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                margin: 0 auto;
                box-shadow: none;
                border-radius: 0;
                padding: 12px 8px;
            }
            @page { margin: 6mm; size: 80mm auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()" type="button">{{ __('Print') }}</button>
        <a href="{{ route('pos.transactions.show', $order) }}" class="secondary">{{ __('Back') }}</a>
    </div>

    <div class="page">
        <div class="brand">
            <img src="{{ asset('images/logo-bright.png') }}" alt="{{ $hotel->name }}">
            <h1>{{ $hotel->name }}</h1>
            @if ($hotel->address)
                <p>{{ $hotel->address }}</p>
            @endif
            @if ($hotel->phone)
                <p>{{ __('Tel:') }} {{ $hotel->phone }}</p>
            @endif
            @if ($hotel->tin)
                <p>{{ __('TIN:') }} {{ $hotel->tin }} @if ($hotel->vrn) · {{ __('VRN:') }} {{ $hotel->vrn }} @endif</p>
            @endif
            <div class="outlet">{{ $order->outlet->name }}</div>
        </div>

        <div class="doc-label">
            <span>{{ __('Sales receipt') }}</span>
        </div>

        <dl class="meta">
            <dt>{{ __('Receipt no.') }}</dt><dd>{{ $order->reference }}</dd>
            <dt>{{ __('Date') }}</dt><dd>{{ ($order->billed_at ?? $order->created_at)?->format('d M Y H:i') }}</dd>
            @if ($order->table_no)
                <dt>{{ __('Table') }}</dt><dd>{{ $order->table_no }}</dd>
            @endif
            <dt>{{ __('Cashier') }}</dt><dd>{{ $order->opened_by ? optional(\App\Models\User::find($order->opened_by))->name ?? '—' : '—' }}</dd>
            <dt>{{ __('Customer') }}</dt><dd>{{ $order->folio?->guest?->fullName() ?? __('Walk-in') }}</dd>
        </dl>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 54%">{{ __('Item') }}</th>
                    <th class="num" style="width: 12%">{{ __('Qty') }}</th>
                    <th class="num" style="width: 34%">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->items as $item)
                    <tr>
                        <td class="desc">
                            {{ $item->name_snapshot }}
                            <br><span class="unit">{{ $item->unit_price->format() }} {{ __('ea.') }}</span>
                        </td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ $item->line_total->format() }}</td>
                    </tr>
                @empty
                    <tr class="empty">
                        <td colspan="3">{{ __('No items on this receipt.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>{{ __('Subtotal (before VAT)') }}</span><span class="value">{{ $order->subtotal->format() }}</span></div>
            <div class="row"><span>{{ $hotel->vat()->label() }}</span><span class="value">{{ $order->tax_total->format() }}</span></div>
            <div class="row grand"><span>{{ __('Total') }}</span><span class="value">{{ $order->total_amount->format() }}</span></div>
        </div>

        @if ($order->folio)
            @php
                $folio = $order->folio;
                $balance = $folio->total_amount->subtract($folio->paid_total);
                $walkIn = $folio->guest_id === null && $folio->stay_id === null;
            @endphp

            @if ($folio->payments->isNotEmpty())
                <div class="payment paid">
                    @foreach ($folio->payments as $payment)
                        <div class="row">
                            <span class="label">
                                {{ __('Paid') }} · {{ str_replace('_', ' ', ucfirst($payment->method_code)) }}
                                @if ($payment->reference) · {{ $payment->reference }} @endif
                            </span>
                            <span class="value">{{ $payment->amountLabel() }}</span>
                        </div>
                        @if ($payment->changeGiven())
                            <div class="row">
                                <span class="label">{{ __('Change') }}</span>
                                <span class="value">{{ $payment->changeGiven()->format() }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @elseif ($walkIn && $balance->isZero())
                <div class="payment paid">
                    <div class="row"><span class="label">{{ __('Paid in full') }}</span><span class="value">{{ $folio->paid_total->format() }}</span></div>
                </div>
            @elseif (! $walkIn)
                <div class="payment">
                    <div class="row">
                        <span class="label">{{ __('Charged to guest billing') }}</span>
                        <span class="value">{{ $folio->reference }}</span>
                    </div>
                    @if ($folio->guest)
                        <div class="row">
                            <span class="label">{{ __('Guest') }}</span>
                            <span class="value" style="font-family:inherit;">{{ $folio->guest->fullName() }}</span>
                        </div>
                    @endif
                </div>
            @endif

            @if ($walkIn && ! $balance->isZero())
                <div class="payment due">
                    <div class="row"><span class="label">{{ __('Balance due') }}</span><span class="value">{{ $balance->format() }}</span></div>
                </div>
            @endif
        @endif

        <div class="footer">
            <div class="thanks">{{ __('Asante sana!') }}</div>
            <div>{{ __('Thank you for your visit.') }}</div>
            @if ($hotel->invoiceFooter)
                <div class="note">{{ $hotel->invoiceFooter->content }}</div>
            @endif
            <div class="ref">{{ $order->reference }}</div>
        </div>
    </div>
</body>
</html>
