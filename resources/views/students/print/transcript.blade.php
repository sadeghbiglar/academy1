<!DOCTYPE html>
<html lang="fa" dir="rtl">
{{--
|| کارنامهٔ رسمی چاپی (issue #37، قاعدهٔ R4).
||
|| بدون layout پنل، مثل چاپِ فاکتور و فیش حقوقی: منو و سایه روی کاغذ نمی‌آیند.
|| این سند برای انسانِ فارسی‌زبان است، پس همهٔ اعداد با to_persian_digits
|| نمایش داده می‌شوند.
||
|| PDF: موتور سمت سرور در این مخزن وجود ندارد و dompdf حروف فارسی را نمی‌بندد؛
|| «ذخیره به‌صورت PDF» همان پنجرهٔ چاپ مرورگر را باز می‌کند که خروجی PDF می‌دهد.
--}}
<head>
    <meta charset="utf-8" />
    <title>کارنامهٔ رسمی {{ $doc['student']->full_name }}</title>
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
        h2 { font-size: 13px; margin: 18px 0 6px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start;
                border-bottom: 2px solid #111; padding-bottom: 10px; }
        .muted { color: #555; }
        .num { font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 5px 7px; text-align: right; }
        th { background: #f2f2f2; font-weight: bold; }
        td.center, th.center { text-align: center; }
        td.left, th.left { text-align: left; }
        .passed { color: #166534; font-weight: bold; }
        .failed { color: #991b1b; font-weight: bold; }
        .pending { color: #555; }
        .totals { margin-top: 10px; }
        .totals td { border: none; padding: 2px 7px; }
        .totals td:last-child { text-align: left; font-weight: bold; }
        .notice { border: 1px dashed #999; padding: 10px; margin-top: 10px; }
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
    <div class="no-print" style="margin-bottom: 10px; display: flex; gap: 8px;">
        <button onclick="window.print()">چاپ</button>
        <button onclick="window.print()" title="در پنجرهٔ چاپ مرورگر «ذخیره به‌صورت PDF» را انتخاب کنید">
            ذخیره به‌صورت PDF
        </button>
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
            <h1>کارنامهٔ رسمی</h1>
            <div class="muted">شماره سند: {{ to_persian_digits($doc['number']) }}</div>
            <div class="muted">تاریخ صدور: {{ jalali_date($doc['issued_at']) }}</div>
        </div>
    </div>

    <table>
        <tr>
            <th style="width: 20%">نام و نام خانوادگی</th>
            <td>{{ $doc['student']->full_name }}</td>
            <th style="width: 20%">کد دانش‌آموزی</th>
            <td>{{ to_persian_digits($doc['student']->profile?->student_code ?? '—') }}</td>
        </tr>
        <tr>
            <th>کد ملی</th>
            <td>{{ to_persian_digits($doc['student']->national_code ?? '—') }}</td>
            <th>تاریخ تولد</th>
            <td>{{ jalali_date($doc['student']->birth_date) ?? '—' }}</td>
        </tr>
    </table>

    @if (! $doc['has_records'])
        <div class="notice">
            <strong>فاقد سابقه</strong>
            برای این دانش‌آموز هنوز نمره‌ای ثبت نشده است؛ این سند بدون سابقهٔ نمرات صادر شده است.
        </div>
    @elseif ($doc['rows']->isEmpty())
        <div class="notice">
            <strong>فاقد سابقه</strong>
            این دانش‌آموز در هیچ دوره‌ای به‌صورت قطعی ثبت‌نام نکرده است.
        </div>
    @else
        <h2>خلاصهٔ دوره‌ها</h2>
        <table>
            <tr>
                <th class="center" style="width: 5%">ردیف</th>
                <th>دوره</th>
                <th>درس</th>
                <th class="center">ساعت</th>
                <th class="center">حضور</th>
                <th class="center">نمرهٔ نهایی</th>
                <th class="center">وضعیت</th>
            </tr>
            @foreach ($doc['rows'] as $index => $row)
                <tr>
                    <td class="center">{{ to_persian_digits($index + 1) }}</td>
                    <td>{{ $row['course']->title }}</td>
                    <td>
                        {{ $row['course']->subject?->name ?? '—' }}
                        @if ($row['course']->teacher)
                            <span class="muted">— {{ $row['course']->teacher->full_name }}</span>
                        @endif
                    </td>
                    <td class="center">{{ to_persian_digits(number_format($row['hours'], 1)) }}</td>
                    <td class="center">
                        {{ $row['attendance'] === null ? '—' : to_persian_digits(number_format($row['attendance'], 1)).'٪' }}
                    </td>
                    <td class="center num">
                        {{ $row['score'] === null ? '—' : to_persian_digits(number_format($row['score'], 2)) }}
                    </td>
                    <td class="center {{ $row['status'] }}">
                        {{ ['passed' => 'قبولی', 'failed' => 'مردود', 'pending' => 'در انتظار نمره'][$row['status']] }}
                    </td>
                </tr>
            @endforeach
        </table>

        <table class="totals">
            <tr>
                <td>جمع ساعت دوره‌ها</td>
                <td>{{ to_persian_digits(number_format($doc['hours'], 1)) }} ساعت</td>
            </tr>
            <tr>
                <td>میانگین کل نمرات نهایی</td>
                <td>
                    {{ $doc['overall'] === null ? '—' : to_persian_digits(number_format($doc['overall'], 2)).' از '.to_persian_digits(\App\Services\Transcript::MAX_SCORE) }}
                </td>
            </tr>
            <tr>
                <td>نتیجه</td>
                <td>
                    {{ to_persian_digits($doc['passed']) }} قبولی
                    — {{ to_persian_digits($doc['failed']) }} مردود
                </td>
            </tr>
        </table>

        <h2>ریزنمرات</h2>
        @foreach ($doc['rows'] as $row)
            @if ($row['grades']->isEmpty())
                <div class="muted" style="margin-top: 6px">
                    {{ $row['course']->title }}: نمره‌ای ثبت نشده است.
                </div>

                @continue
            @endif

            <div class="muted" style="margin-top: 8px; font-weight: bold">
                {{ $row['course']->title }}
            </div>
            <table>
                <tr>
                    <th style="width: 18%">نوع</th>
                    <th>عنوان</th>
                    <th class="center" style="width: 20%">نمره</th>
                    <th class="center" style="width: 18%">درصد</th>
                    <th class="center" style="width: 18%">تاریخ</th>
                </tr>
                @foreach ($row['grades'] as $grade)
                    <tr>
                        <td>{{ $grade->typeLabel() }}</td>
                        <td>{{ $grade->title }}</td>
                        <td class="center num">{{ $grade->scoreLabel() }}</td>
                        <td class="center">{{ to_persian_digits(number_format($grade->percentage(), 1)).'٪' }}</td>
                        <td class="center">{{ $grade->gradedAtJalali() ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endforeach

        <div class="foot">
            نمرهٔ قبولی {{ to_persian_digits(number_format($doc['pass_score'], 0)) }} از
            {{ to_persian_digits(\App\Services\Transcript::MAX_SCORE) }} است.
            نمرهٔ نهایی هر دوره میانگین وزنی انواع نمره است، نه میانگین سادهٔ ریزنمرات.
        </div>
    @endif

    <div class="signatures">
        <div>امضای دانش‌آموز</div>
        <div>امضای و تأیید مدیر آموزشگاه</div>
    </div>

    <div class="foot">
        این کارنامه در {{ jalali_date($doc['issued_at']) }} صادر شده است.
        شمارهٔ سند {{ to_persian_digits($doc['number']) }} است و با هر بار چاپِ دوبارهٔ همان
        دانش‌آموز در همان سال، همین شماره را دارد.
    </div>
</body>
</html>
