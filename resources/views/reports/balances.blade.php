@extends('layouts.academy')

@section('title', 'گزارش وصولی و بدهکاران')

<div>
    <h1 class="text-2xl font-bold mb-4">گزارش وصولی و بدهکاران</h1>

    <form method="GET" action="{{ route('reports.balances') }}"
          class="bg-base-100 rounded-box shadow p-4 mb-6 flex flex-wrap items-end gap-3">
        <label class="form-control">
            <span class="label-text mb-1">از تاریخ (شمسی)</span>
            <input type="text" name="from" value="{{ $from }}" placeholder="۱۴۰۵/۰۷/۰۱"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>

        <label class="form-control">
            <span class="label-text mb-1">تا تاریخ (شمسی)</span>
            <input type="text" name="to" value="{{ $to }}" placeholder="۱۴۰۵/۰۷/۳۰"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>

        <label class="label cursor-pointer gap-2">
            <input type="checkbox" name="only_debtors" value="1" class="checkbox checkbox-sm"
                   @checked($onlyDebtors) />
            <span class="label-text">فقط بدهکاران</span>
        </label>

        <button type="submit" class="btn btn-sm btn-primary">اعمال بازه</button>

        @if ($from || $to || $onlyDebtors)
            <a href="{{ route('reports.balances') }}" class="btn btn-sm btn-ghost">حذف فیلتر</a>
        @endif
    </form>

    <div class="stats stats-vertical sm:stats-horizontal shadow mb-6">
        <div class="stat">
            <div class="stat-title">جمع فاکتورها</div>
            <div class="stat-value text-lg">{{ to_persian_digits(number_format($totals['total'])) }}</div>
            <div class="stat-desc">تومان</div>
        </div>
        <div class="stat">
            <div class="stat-title">جمع وصولی</div>
            <div class="stat-value text-lg text-success">{{ to_persian_digits(number_format($totals['paid'])) }}</div>
            <div class="stat-desc">تومان</div>
        </div>
        <div class="stat">
            <div class="stat-title">ماندهٔ کل</div>
            <div class="stat-value text-lg text-error">{{ to_persian_digits(number_format($totals['outstanding'])) }}</div>
            <div class="stat-desc">تومان</div>
        </div>
        <div class="stat">
            <div class="stat-title">ماندهٔ کل آموزشگاه</div>
            <div class="stat-value text-lg">{{ to_persian_digits(number_format(max(0, $totalOutstanding))) }}</div>
            <div class="stat-desc">تومان</div>
        </div>
    </div>

    @if (count($byMethod) > 0)
        <x-card title="وصولی به تفکیک روش پرداخت" class="mb-6">
            <div class="grid sm:grid-cols-3 gap-3 text-sm">
                @foreach ($byMethod as $label => $total)
                    <div class="flex justify-between border-b border-base-200 pb-2">
                        <span>{{ $label }}</span>
                        <span class="font-medium">{{ to_persian_digits(number_format($total)) }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <div class="bg-base-100 rounded-box shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>دانش‌آموز</th>
                        <th>کد ملی</th>
                        <th>جمع فاکتور</th>
                        <th>پرداخت‌شده</th>
                        <th>مانده</th>
                        <th>آخرین پرداخت</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['student']->full_name }}</td>
                            <td>{{ to_persian_digits($row['student']->national_code ?? '—') }}</td>
                            <td>{{ to_persian_digits(number_format($row['total'])) }}</td>
                            <td class="text-success">{{ to_persian_digits(number_format($row['paid'])) }}</td>
                            <td class="{{ $row['outstanding'] > 0 ? 'text-error font-medium' : '' }}">
                                {{ to_persian_digits(number_format($row['outstanding'])) }}
                            </td>
                            <td>{{ $row['last_paid_at'] ? jalali_date($row['last_paid_at']) : '—' }}</td>
                            <td>
                                <span class="badge badge-sm {{ match ($row['status']) {
                                    'تسویه' => 'badge-success',
                                    'نیمه‌پرداخت' => 'badge-warning',
                                    'بدهکار' => 'badge-error',
                                    default => 'badge-ghost',
                                } }}">{{ $row['status'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center opacity-60 py-6">داده‌ای برای این بازه نیست.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-medium">
                            <td colspan="2">جمع</td>
                            <td>{{ to_persian_digits(number_format($totals['total'])) }}</td>
                            <td>{{ to_persian_digits(number_format($totals['paid'])) }}</td>
                            <td>{{ to_persian_digits(number_format($totals['outstanding'])) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
