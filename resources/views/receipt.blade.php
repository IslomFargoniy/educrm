@php
    $enrollment = $payment->enrollment;
    $money = fn ($v) => number_format((int) $v, 0, '.', ' ');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt') }} {{ $payment->receipt_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Manrope', Arial, sans-serif; color: #000; }
        .receipt { margin: 0 auto; padding: 8px; }
        .thermal { width: {{ $width }}mm; font-size: {{ $width === 58 ? '11px' : '12px' }}; }
        .a4 { width: 180mm; padding: 16mm 0; font-size: 14px; }
        .center { text-align: center; }
        .brand { font-weight: 800; font-size: 1.6em; letter-spacing: -0.02em; }
        .muted { color: #444; }
        .row { display: flex; justify-content: space-between; gap: 8px; padding: 2px 0; }
        .row span:last-child { text-align: right; font-weight: 600; }
        .total { font-size: 1.5em; font-weight: 800; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 6px 0; margin: 6px 0; }
        .a4 .head { display: flex; align-items: center; gap: 12px; border-bottom: 3px solid #0b0f7e; padding-bottom: 12px; margin-bottom: 16px; }
        .a4 .total { color: #0b0f7e; border-color: #0b0f7e; }
        hr { border: 0; border-top: 1px dashed #000; }
        @media print { .no-print { display: none; } @page { margin: {{ $format === 'a4' ? '10mm' : '0' }}; } }
    </style>
</head>
<body>
<div class="receipt {{ $format }}">
    @if ($format === 'a4')
        <div class="head">
            <svg width="48" height="48" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0B0F7E"/><g transform="translate(6 6) scale(0.8125)" fill="#fff"><polygon points="8,16 28.5,23 28.5,58.5 8,47"/><polygon points="56,16 35.5,23 35.5,58.5 56,47"/><polygon points="30.5,24 33.5,24 33.5,59.5 32,61 30.5,59.5"/><polygon points="32,5 41.5,11.5 32,18 22.5,11.5" fill="#F7941D"/></g></svg>
            <div>
                <div class="brand" style="color:#0b0f7e">Mensa</div>
                <div class="muted">Learning center · {{ $enrollment->group->branch->name }} {{ $enrollment->group->branch->phone }}</div>
            </div>
        </div>
    @else
        <div class="center">
            <div class="brand">MENSA</div>
            <div class="muted">Learning Center</div>
            <div class="muted">{{ $enrollment->group->branch->name }}</div>
            @if ($enrollment->group->branch->phone)<div class="muted">{{ $enrollment->group->branch->phone }}</div>@endif
        </div>
        <hr>
    @endif

    <div class="row"><span>{{ __('Receipt') }} №</span><span>{{ $payment->receipt_no }}</span></div>
    <div class="row"><span>{{ __('Date') }}</span><span>{{ $payment->paid_at->format('d.m.Y H:i') }}</span></div>
    <div class="row"><span>{{ __('Student') }}</span><span>{{ $enrollment->student->full_name }}</span></div>
    <div class="row"><span>{{ __('Group') }}</span><span>{{ $enrollment->group->name }}</span></div>
    <div class="row"><span>{{ __('Month') }}</span><span>{{ $payment->paid_at->translatedFormat('F Y') }}</span></div>
    <div class="row"><span>{{ __('Payment method') }}</span><span>{{ $payment->cashbox->name }}</span></div>
    <div class="row total"><span>{{ __('Amount') }}</span><span>{{ $money($payment->amount) }} {{ __('sum') }}</span></div>
    @if ($balanceAfter !== null)
        <div class="row"><span>{{ __('Balance after payment') }}</span><span>{{ $money($balanceAfter) }} {{ __('sum') }}</span></div>
    @endif
    <div class="row"><span>{{ __('Cashier') }}</span><span>{{ $payment->receiver?->name }}</span></div>
    @if ($payment->reversed_at)
        <div class="center" style="font-weight:800;margin-top:8px">{{ __('CANCELLED') }}</div>
    @endif
    <hr>
    <div class="center muted">{{ __('Thank you!') }}</div>

    <div class="no-print center" style="margin-top:16px">
        <button onclick="window.print()" style="padding:8px 16px;font-size:14px">{{ __('Print') }}</button>
    </div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
