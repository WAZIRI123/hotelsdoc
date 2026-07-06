<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Bill') }} {{ $folio->reference }} — {{ $hotel->name }}</title>
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
            max-width: 820px;
            margin: 32px auto;
            background: #ffffff;
            padding: 48px 56px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 6px;
        }

        .toolbar {
            max-width: 820px;
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

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 2px solid #111827;
        }
        .brand { display: flex; align-items: flex-start; gap: 16px; }
        .brand img {
            width: 88px;
            height: auto;
            background: #111827;
            padding: 8px;
            border-radius: 6px;
        }
        .brand-info h1 {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        .brand-info p { margin: 2px 0; color: #4b5563; font-size: 12px; }

        .doc-meta { text-align: right; }
        .doc-meta .label {
            display: inline-block;
            background: #111827;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 600;
        }
        .doc-meta dl {
            margin: 12px 0 0;
            display: grid;
            grid-template-columns: auto auto;
            gap: 4px 12px;
            font-size: 12px;
        }
        .doc-meta dt { color: #6b7280; text-align: right; }
        .doc-meta dd { margin: 0; font-family: 'Courier New', monospace; font-weight: 600; }

        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }
        .party h3 {
            margin: 0 0 8px;
            font-size: 11px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
        }
        .party .name { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .party p { margin: 2px 0; font-size: 12px; color: #374151; }

        .stay-summary {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            padding: 16px;
            background: #F3F3F3;
            border-radius: 6px;
        }
        .stay-summary .cell .label {
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
        }
        .stay-summary .cell .value {
            font-size: 13px;
            font-weight: 600;
            margin-top: 2px;
            color: #111827;
        }

        .section-title {
            margin: 32px 0 8px;
            font-size: 12px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        table.items thead th {
            text-align: left;
            padding: 8px 10px;
            background: #111827;
            color: #fff;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        table.items thead th.num { text-align: right; }
        table.items tbody td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.items tbody td.num {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        table.items tbody td.desc { font-weight: 500; }
        table.items tbody tr:last-child td { border-bottom: none; }
        table.items tbody tr.empty td {
            text-align: center;
            color: #9ca3af;
            padding: 16px;
        }

        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 320px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }
        .totals .row.sub { border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 4px; }
        .totals .row .value { font-family: 'Courier New', monospace; }
        .totals .row.grand {
            margin-top: 6px;
            padding: 10px 12px;
            background: #111827;
            color: #fff;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 600;
        }
        .totals .row.due {
            margin-top: 6px;
            padding: 10px 12px;
            background: #dc2626;
            color: #fff;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }
        .totals .row.paidfull {
            margin-top: 6px;
            padding: 10px 12px;
            background: #16a34a;
            color: #fff;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }

        .signatures {
            margin-top: 48px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
        }
        .signatures .line {
            border-top: 1px solid #111827;
            padding-top: 8px;
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }
        .footer .thanks { font-size: 13px; color: #111827; font-weight: 600; margin-bottom: 4px; }

        .receipt-note {
            margin-top: 16px;
            padding: 10px 12px;
            border-left: 3px solid #16a34a;
            background: #f0fdf4;
            font-size: 12px;
            color: #166534;
        }
        .receipt-note .code { font-family: 'Courier New', monospace; font-weight: 600; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
                padding: 24px 32px;
            }
            @page { margin: 12mm; size: A4; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()" type="button">{{ __('Print bill') }}</button>
        <a href="{{ route('folios.show', $folio) }}" class="secondary">{{ __('Back') }}</a>
    </div>

    <div class="page">
        <div class="header">
            <div class="brand">
                <img src="{{ asset('images/logo-bright.png') }}" alt="{{ $hotel->name }}">
                <div class="brand-info">
                    <h1>{{ $hotel->name }}</h1>
                    @if ($hotel->address)
                        <p>{{ $hotel->address }}</p>
                    @endif
                    @if ($hotel->phone)
                        <p>{{ __('Tel:') }} {{ $hotel->phone }}</p>
                    @endif
                    @if ($hotel->email)
                        <p>{{ $hotel->email }}</p>
                    @endif
                    @if ($hotel->tin)
                        <p>{{ __('TIN:') }} {{ $hotel->tin }} @if ($hotel->vrn) · {{ __('VRN:') }} {{ $hotel->vrn }} @endif</p>
                    @endif
                </div>
            </div>

            <div class="doc-meta">
                <span class="label">{{ __('Bill') }}</span>
                <dl>
                    <dt>{{ __('No.') }}</dt><dd>{{ $folio->reference }}</dd>
                    <dt>{{ __('Date') }}</dt><dd>{{ now()->format('d M Y') }}</dd>
                    <dt>{{ __('Status') }}</dt><dd>{{ ucfirst($folio->status) }}</dd>
                    @if ($folio->stay?->reservation)
                        <dt>{{ __('Res.') }}</dt><dd>{{ $folio->stay->reservation->confirmation_no }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <h3>{{ __('Billed to') }}</h3>
                <div class="name">{{ $folio->guest?->fullName() ?? __('Walk-in guest') }}</div>
                @if ($folio->guest?->phone)
                    <p>{{ $folio->guest->phone }}</p>
                @endif
                @if ($folio->guest?->email)
                    <p>{{ $folio->guest->email }}</p>
                @endif
                @if ($folio->guest?->address)
                    <p>{{ $folio->guest->address }}</p>
                @endif
                @if ($folio->guest?->id_type && $folio->guest?->id_number)
                    <p>{{ ucfirst(str_replace('_', ' ', $folio->guest->id_type)) }}: {{ $folio->guest->id_number }}</p>
                @endif
            </div>

            @if ($folio->stay?->reservation)
                @php $res = $folio->stay->reservation; @endphp
                <div class="party">
                    <h3>{{ __('Reservation') }}</h3>
                    <div class="name">{{ $res->confirmation_no }}</div>
                    <p>{{ __('Arrival:') }} {{ $res->arrival->format('d M Y') }}</p>
                    <p>{{ __('Departure:') }} {{ $res->departure->format('d M Y') }}</p>
                    <p>{{ __('Guests:') }} {{ $res->adults }} {{ __('adults') }}@if ($res->children), {{ $res->children }} {{ __('children') }}@endif</p>
                    @if ($res->rooms->isNotEmpty())
                        <p>{{ __('Room:') }}
                            {{ $res->rooms->map(fn ($r) => $r->roomType?->name ?? '—')->filter()->join(', ') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($folio->stay)
            @php
                $nights = $folio->stay->reservation
                    ? max(1, $folio->stay->reservation->arrival->diffInDays($folio->stay->reservation->departure))
                    : null;
            @endphp
            <div class="stay-summary">
                <div class="cell">
                    <div class="label">{{ __('Checked in') }}</div>
                    <div class="value">{{ $folio->stay->checked_in_at?->format('d M Y H:i') ?? '—' }}</div>
                </div>
                <div class="cell">
                    <div class="label">{{ __('Checked out') }}</div>
                    <div class="value">{{ $folio->stay->checked_out_at?->format('d M Y H:i') ?? __('In-house') }}</div>
                </div>
                <div class="cell">
                    <div class="label">{{ __('Nights') }}</div>
                    <div class="value">{{ $nights ?? '—' }}</div>
                </div>
                <div class="cell">
                    <div class="label">{{ __('Currency') }}</div>
                    <div class="value">{{ $folio->currency }}</div>
                </div>
            </div>
        @endif

        <div class="section-title">{{ __('Itemized charges') }}</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 8%">#</th>
                    <th>{{ __('Description') }}</th>
                    <th class="num" style="width: 10%">{{ __('Qty') }}</th>
                    <th class="num" style="width: 20%">{{ __('Unit price') }}</th>
                    <th class="num" style="width: 22%">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($folio->charges as $i => $charge)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="desc">
                            {{ $charge->description }}
                            @if ($charge->posted_at)
                                <br><span style="color:#9ca3af;font-size:11px;">{{ $charge->posted_at->format('d M Y H:i') }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $charge->quantity }}</td>
                        <td class="num">{{ $charge->unit_price->format() }}</td>
                        <td class="num">{{ $charge->total->format() }}</td>
                    </tr>
                @empty
                    <tr class="empty">
                        <td colspan="5">{{ __('No charges recorded.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @php $balance = $folio->total_amount->subtract($folio->paid_total); @endphp
        <div class="totals">
            <div class="row"><span>{{ __('Subtotal') }}</span><span class="value">{{ $folio->subtotal->format() }}</span></div>
            <div class="row"><span>{{ __('Tax') }}</span><span class="value">{{ $folio->tax_total->format() }}</span></div>
            <div class="row grand"><span>{{ __('Total') }}</span><span class="value">{{ $folio->total_amount->format() }}</span></div>
            <div class="row sub"><span>{{ __('Amount paid') }}</span><span class="value">{{ $folio->paid_total->format() }}</span></div>
            @if ($balance->isZero())
                <div class="row paidfull"><span>{{ __('Paid in full') }}</span><span class="value">{{ $balance->format() }}</span></div>
            @else
                <div class="row due"><span>{{ __('Balance due') }}</span><span class="value">{{ $balance->format() }}</span></div>
            @endif
        </div>

        @if ($folio->payments->isNotEmpty())
            <div class="section-title">{{ __('Payments received') }}</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($folio->payments as $payment)
                        <tr>
                            <td>{{ ($payment->confirmed_at ?? $payment->created_at)?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="desc">{{ str_replace('_', ' ', ucfirst($payment->method_code)) }}</td>
                            <td>{{ $payment->reference ?? '—' }}</td>
                            <td class="num">{{ $payment->amount->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @php
            $receipt = \App\Models\FiscalReceipt::query()
                ->where('folio_id', $folio->id)
                ->where('status', 'issued')
                ->latest()
                ->first();
        @endphp
        @if ($receipt)
            <div class="receipt-note">
                {{ __('Fiscal receipt') }} <span class="code">{{ $receipt->receipt_no }}</span>
                @if ($receipt->tra_verification_code)
                    · {{ __('TRA verification:') }} <span class="code">{{ $receipt->tra_verification_code }}</span>
                @endif
            </div>
        @endif

        <div class="signatures">
            <div class="line">{{ __('Guest signature') }}</div>
            <div class="line">{{ __('Cashier signature') }}</div>
        </div>

        <div class="footer">
            <div class="thanks">{{ __('Thank you for your stay!') }}</div>
            <div>{{ __('This bill was generated on') }} {{ now()->format('d M Y H:i') }} · {{ $folio->reference }}</div>
        </div>
    </div>
</body>
</html>
