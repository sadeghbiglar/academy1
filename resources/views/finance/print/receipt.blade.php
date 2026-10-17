<!DOCTYPE html>
<html lang="fa" dir="rtl">
{{--
| رسید پرداخت چاپی.
|
| یک برگهٔ کوچک، نه یک صفحه: رسید را کارت‌خوان یا کاغذ کوچک چاپ می‌کنند و
| همراه دانش‌آموز می‌فرستند.
--}}
<head>
    <meta charset="utf-8" />
    <title>رسید پرداخت</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            width: 80mm;
            margin: 0 auto;
            padding: 6mm 4mm;
            color: #111;
            font-size: 11px;
            line-height: 1.9;
        }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .center { text-align: center; }
        .muted { color: #555; }
        hr { border: none; border-top: 1px dashed #999; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; }
        .amount { font-size: 14px; font-weight: bold; border: 1px solid #333; padding: 6px; margin-top: 8px; }
        .foot { margin-top: 10px; font-size: 9px; color: #555; text-align: center; }
        @media print {
            .no-print { display: none; }
            body { width: auto; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print center" style="margin-bottom: 6px;">
        <button onclick="window.print()">چاپ</button>
    </div>

    <div class="center">
        <h1>{{ \App\Models\Setting::academyName() }}</h1>
        @if (\App\Models\Setting::academyPhone())
            <div class="muted">تلفن: {{ \App\Models\Setting::academyPhone() }}</div>
        @endif
        <div><strong>رسید پرداخت</strong></div>
        <div class="muted">شمارهٔ فاکتور: {{ $payment->invoice->number }}</div>
    </div>

    <hr />

    <div class="row"><span>تاریخ:</span><span>{{ $payment->paidAtJalali() }}</span></div>
    <div class="row"><span>روش پرداخت:</span><span>{{ $payment->methodLabel() }}</span></div>
    @if ($payment->reference)
        <div class="row"><span>شمارهٔ پیگیری:</span><span>{{ $payment->reference }}</span></div>
    @endif
    <div class="row"><span>دانش‌آموز:</span><span>{{ $payment->invoice->student?->full_name ?? '—' }}</span></div>
    <div class="row"><span>بابت:</span><span>{{ $payment->invoice->course?->title ?? 'شهریه' }}</span></div>

    <div class="amount center">
        {{ to_persian_digits(number_format(abs($payment->amount))) }} تومان
    </div>

    <hr />

    <div class="row"><span>مانده فاکتور:</span><span>{{ to_persian_digits(number_format($payment->invoice->remaining())) }} تومان</span></div>
    @if ($payment->invoice->credit() > 0)
        <div class="row"><span>اعتبار بیش از حد:</span><span>{{ to_persian_digits(number_format($payment->invoice->credit())) }} تومان</span></div>
    @endif

    @if ($payment->notes)
        <hr />
        <div>{{ $payment->notes }}</div>
    @endif

    <div class="foot">
        ثبت‌کننده: {{ $payment->user?->display_name ?? '—' }}
    </div>
</body>
</html>
