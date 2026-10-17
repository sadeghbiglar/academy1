<!DOCTYPE html>
<html lang="fa" dir="rtl">
{{--
| فاکتور چاپی.
|
| این صفحه عمداً layout پنل را ندارد: منو، سایه و دکمه‌ها روی کاغذ می‌افتند
| و رسیدی که نیمه‌اش منو است، سند مالی نیست.
--}}
<head>
    <meta charset="utf-8" />
    <title>فاکتور {{ $invoice->number }}</title>
    <style>
        @font-face { font-family: 'Vazirmatn'; src: local('Vazirmatn'); }
        * { box-sizing: border-box; }
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            margin: 0;
            padding: 16mm 14mm;
            color: #111;
            font-size: 12px;
            line-height: 1.9;
        }
        h1 { font-size: 18px; margin: 0; }
        .head { display: flex; justify-content: space-between; align-items: flex-start;
                border-bottom: 2px solid #111; padding-bottom: 10px; }
        .muted { color: #555; }
        .num { font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 5px 7px; text-align: right; }
        th { background: #f2f2f2; font-weight: bold; }
        .box { border: 1px solid #999; padding: 8px; margin-top: 12px; }
        .totals td { border: none; padding: 2px 7px; }
        .totals td:last-child { text-align: left; font-weight: bold; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
        .signatures div { width: 45%; border-top: 1px solid #333; padding-top: 5px; text-align: center; }
        .foot { margin-top: 14px; border-top: 1px dashed #999; padding-top: 6px; font-size: 10px; color: #555; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px;">
        <button onclick="window.print()">چاپ</button>
    </div>

    <div class="head">
        <div>
            <h1>{{ \App\Models\Setting::academyName() }}</h1>
            <div class="muted">
                @if (\App\Models\Setting::academyPhone())
                    تلفن: {{ \App\Models\Setting::academyPhone() }}
                @endif
                @if (\App\Models\Setting::academyAddress())
                    — {{ \App\Models\Setting::academyAddress() }}
                @endif
            </div>
        </div>
        <div style="text-align: left;">
            <div class="num">فاکتور: {{ $invoice->number }}</div>
            <div class="muted">تاریخ صدور: {{ $invoice->issuedAtJalali() ?? '—' }}</div>
            <div class="muted">موعد پرداخت: {{ $invoice->dueAtJalali() ?? '—' }}</div>
            <div>وضعیت: {{ $invoice->statusLabel() }}</div>
        </div>
    </div>

    <table style="margin-top: 14px;">
        <tr>
            <th style="width: 90px;">دانش‌آموز</th>
            <td>{{ $invoice->student?->full_name ?? '—' }}</td>
            <th style="width: 90px;">کد دانش‌آموزی</th>
            <td>{{ $invoice->student?->profile?->codeLabel() ?? '—' }}</td>
        </tr>
        <tr>
            <th>دوره</th>
            <td>{{ $invoice->course?->title ?? '—' }}</td>
            <th>کد ملی</th>
            <td>{{ $invoice->student?->national_code ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>شرح</th>
                <th style="width: 120px;">مبلغ</th>
                <th style="width: 120px;">تخفیف</th>
                <th style="width: 120px;">قابل پرداخت</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->course?->title ?? 'شهریهٔ دورهٔ آموزشگاه' }}</td>
                <td>{{ to_persian_digits(number_format($invoice->amount)) }}</td>
                <td>{{ to_persian_digits(number_format($invoice->discount_amount)) }}</td>
                <td>{{ to_persian_digits(number_format($invoice->payable)) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="box">
        <table class="totals">
            <tr><td>قابل پرداخت:</td><td>{{ to_persian_digits(number_format($invoice->payable)) }} تومان</td></tr>
            <tr><td>پرداخت‌شده تا این لحظه:</td><td>{{ to_persian_digits(number_format($invoice->paidAmount())) }} تومان</td></tr>
            <tr><td>مانده بدهی:</td><td>{{ to_persian_digits(number_format($invoice->remaining())) }} تومان</td></tr>
            @if ($invoice->credit() > 0)
                <tr><td>اعتبار بیش از حد پرداختی:</td><td>{{ to_persian_digits(number_format($invoice->credit())) }} تومان</td></tr>
            @endif
        </table>
    </div>

    @if ($invoice->hasInstallmentPlan())
        <div class="box">
            <strong>برنامهٔ اقساط</strong>
            <table>
                <thead>
                    <tr><th>قسط</th><th>سررسید</th><th>مبلغ (تومان)</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoice->installments()->orderBy('sequence')->get() as $installment)
                        <tr>
                            <td>{{ to_persian_digits($installment->sequence) }}</td>
                            <td>{{ $installment->dueAtJalali() }}</td>
                            <td>{{ to_persian_digits(number_format($installment->amount)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($invoice->payments->isNotEmpty())
        <div class="box">
            <strong>سوابق پرداخت</strong>
            <table>
                <thead>
                    <tr><th>تاریخ</th><th>روش</th><th>پیگیری</th><th>مبلغ (تومان)</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments->sortBy('paid_at') as $payment)
                        <tr>
                            <td>{{ $payment->paidAtJalali() }}</td>
                            <td>{{ $payment->methodLabel() }}</td>
                            <td>{{ $payment->reference ?? '—' }}</td>
                            <td>{{ to_persian_digits(number_format(abs($payment->amount))) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($invoice->notes)
        <div class="box"><strong>توضیحات:</strong> {{ $invoice->notes }}</div>
    @endif

    <div class="signatures">
        <div>امضای پرداخت‌کننده</div>
        <div>امضای آموزشگاه</div>
    </div>

    <div class="foot">
        صادرشده توسط {{ $issuedBy?->display_name ?? 'سامانه' }} —
        این سند با داده‌های سامانه تولید شده و معتبر است.
    </div>
</body>
</html>
