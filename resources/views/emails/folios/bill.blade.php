<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Bill') }} {{ $folio->reference }}</title>
</head>
<body style="margin:0;padding:0;background:#F3F3F3;font-family:'Segoe UI',Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F3F3;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;padding:32px;max-width:640px;">
                    <tr>
                        <td style="padding-bottom:16px;border-bottom:2px solid #111827;">
                            <h1 style="margin:0;font-size:22px;font-weight:600;color:#111827;">{{ $hotel->name }}</h1>
                            @if ($hotel->address)
                                <p style="margin:4px 0 0;color:#4b5563;font-size:12px;">{{ $hotel->address }}</p>
                            @endif
                            @if ($hotel->phone)
                                <p style="margin:2px 0;color:#4b5563;font-size:12px;">{{ __('Tel:') }} {{ $hotel->phone }}</p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 0 8px;">
                            <p style="margin:0;font-size:13px;color:#374151;">
                                {{ __('Dear') }} {{ $folio->guest?->fullName() ?? __('Guest') }},
                            </p>
                            <p style="margin:8px 0 0;font-size:13px;color:#374151;">
                                {{ __('Please find your bill below. Reference:') }}
                                <strong style="font-family:'Courier New',monospace;">{{ $folio->reference }}</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;margin-top:16px;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding:8px 10px;background:#111827;color:#fff;font-size:11px;letter-spacing:0.05em;text-transform:uppercase;">{{ __('Description') }}</th>
                                        <th align="right" style="padding:8px 10px;background:#111827;color:#fff;font-size:11px;letter-spacing:0.05em;text-transform:uppercase;">{{ __('Qty') }}</th>
                                        <th align="right" style="padding:8px 10px;background:#111827;color:#fff;font-size:11px;letter-spacing:0.05em;text-transform:uppercase;">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($folio->charges as $charge)
                                        <tr>
                                            <td style="padding:10px;border-bottom:1px solid #e5e7eb;">{{ $charge->description }}</td>
                                            <td align="right" style="padding:10px;border-bottom:1px solid #e5e7eb;font-family:'Courier New',monospace;">{{ $charge->quantity }}</td>
                                            <td align="right" style="padding:10px;border-bottom:1px solid #e5e7eb;font-family:'Courier New',monospace;">{{ $charge->total->format() }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" align="center" style="padding:16px;color:#9ca3af;">{{ __('No charges recorded.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    @php $balance = $folio->total_amount->subtract($folio->paid_total); @endphp
                    <tr>
                        <td style="padding-top:20px;">
                            <table role="presentation" width="320" align="right" cellpadding="0" cellspacing="0" style="font-size:13px;">
                                <tr>
                                    <td style="padding:6px 0;">{{ __('Subtotal (before VAT)') }}</td>
                                    <td align="right" style="padding:6px 0;font-family:'Courier New',monospace;">{{ $folio->subtotal->format() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;">{{ $hotel->vat()->label() }}</td>
                                    <td align="right" style="padding:6px 0;font-family:'Courier New',monospace;">{{ $folio->tax_total->format() }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:10px 12px;background:#111827;color:#fff;border-radius:4px;font-weight:600;">
                                        <table role="presentation" width="100%"><tr>
                                            <td style="color:#fff;">{{ __('Total') }}</td>
                                            <td align="right" style="color:#fff;font-family:'Courier New',monospace;">{{ $folio->total_amount->format() }}</td>
                                        </tr></table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0 6px;border-top:1px solid #e5e7eb;">{{ __('Amount paid') }}</td>
                                    <td align="right" style="padding:10px 0 6px;border-top:1px solid #e5e7eb;font-family:'Courier New',monospace;">{{ $folio->paid_total->format() }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:10px 12px;background:{{ $balance->isZero() ? '#16a34a' : '#dc2626' }};color:#fff;border-radius:4px;font-weight:600;">
                                        <table role="presentation" width="100%"><tr>
                                            <td style="color:#fff;">{{ $balance->isZero() ? __('Paid in full') : __('Balance due') }}</td>
                                            <td align="right" style="color:#fff;font-family:'Courier New',monospace;">{{ $balance->format() }}</td>
                                        </tr></table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:80px;">
                            <p style="margin:0;font-size:13px;color:#111827;font-weight:600;">{{ __('Thank you for your stay!') }}</p>
                            <p style="margin:8px 0 0;font-size:11px;color:#6b7280;">
                                {{ __('Generated on') }} {{ now()->format('d M Y H:i') }} · {{ $folio->reference }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
