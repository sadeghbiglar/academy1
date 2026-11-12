<!DOCTYPE html>
<html lang="fa" dir="rtl">
{{--
| فیش حقوقی چاپی (شماره 40).
|
| بدون layout پنل، مثل چاپِ فاکتور: منو و سایه روی کاغذ نمی‌آیند.
| اعداد همه با to_persian_digits نمایش داده می‌شوند چون این سند برای
| انسانِ فارسی‌زبان است، نه برای پردازش بعدی.
--}}
<head>
    <meta charset="utf-8" />
    <title>فیش حقوقی {{ $payroll->periodLabel() }}</title>
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
        .totals td { border: none; padding: 2px 7px; }
        .totals td:last-child { text-align: left; font-weight: bold; }
        .net { font-size: 15px; font-weight: bold; }
        .signatures { display: flex; justify-content: space-between; margin-top: 48px; }
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
        <div style="text-align: left">
            <h1>فیش حقوقی</h1>
            <div class="muted">دوره: {{ $payroll->periodLabel() }}</div>
            <div class="muted">شماره فیش: {{ to_persian_digits($payroll->id) }}</div>
        </div>
    </div>

    <table>
        <tr>
            <th style="width: 25%">استاد</th>
            <td>{{ $payroll->teacher?->full_name ?? '—' }}</td>
            <th style="width: 25%">وضعیت پرداخت</th>
            <td>{{ $payroll->statusLabel() }}</td>
        </tr>
        <tr>
            <th>روزهای کارکرد</th>
            <td>{{ to_persian_digits($payroll->days_worked) }} روز</td>
            <th>اضافه‌کاری</th>
            <td>{{ to_persian_digits($payroll->overtime_hours) }} ساعت</td>
        </tr>
        @if ($payroll->paid_at)
            <tr>
                <th>تاریخ پرداخت</th>
                <td colspan="3">{{ jalali_date($payroll->paid_at) }}</td>
            </tr>
        @endif
    </table>

    <table>
        <tr>
            <th>شرح</th>
            <th style="text-align: left">مبلغ (تومان)</th>
        </tr>
        <tr>
            <td>مبلغ ناخالص</td>
            <td style="text-align: left" class="num">{{ $payroll->grossLabel() }}</td>
        </tr>
        <tr>
            <td>کسورات بیمه</td>
            <td style="text-align: left" class="num">− {{ to_persian_digits(number_format($payroll->insurance)) }}</td>
        </tr>
        <tr>
            <td>کسورات مالیات</td>
            <td style="text-align: left" class="num">− {{ to_persian_digits(number_format($payroll->tax)) }}</td>
        </tr>
        <tr>
            <td>مبلغ خالص قابل پرداخت</td>
            <td style="text-align: left" class="num net">{{ $payroll->netLabel() }}</td>
        </tr>
    </table>

    <div class="signatures">
        <div>امضای استاد</div>
        <div>امضای مدیر آموزشگاه</div>
    </div>

    <div class="foot">
        این فیش در {{ jalali_date($payroll->created_at) }} صادر شده است.
        مبلغ خالص از کسرِ کسوراتِ بیمه و مالیات از مبلغ ناخالص به دست آمده است.
    </div>
</body>
</html>
