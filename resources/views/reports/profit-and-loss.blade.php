@extends('layouts.academy')

@section('title', 'صورت سود و زیان')

<div>
    <h1 class="text-2xl font-bold mb-4">صورت سود و زیان</h1>

    <form method="GET" action="{{ route('reports.profit-and-loss') }}"
          class="bg-base-100 rounded-box shadow p-4 mb-6 flex flex-wrap items-end gap-3">
        <label class="form-control">
            <span class="label-text mb-1">از تاریخ (شمسی)</span>
            <input type="text" name="from" value="{{ $from }}" placeholder="۱۴۰۵/۰۱/۰۱"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>
        <label class="form-control">
            <span class="label-text mb-1">تا تاریخ (شمسی)</span>
            <input type="text" name="to" value="{{ $to }}" placeholder="۱۴۰۵/۱۲/۲۹"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>
        <button type="submit" class="btn btn-sm btn-primary">اعمال بازه</button>
        @if ($from || $to)
            <a href="{{ route('reports.profit-and-loss') }}" class="btn btn-sm btn-ghost">حذف فیلتر</a>
        @endif
    </form>

    <div class="stats stats-vertical sm:stats-horizontal shadow mb-6">
        <div class="stat">
            <div class="stat-title">درآمد (وصولی)</div>
            <div class="stat-value text-lg text-success">{{ to_persian_digits(number_format($income)) }}</div>
            <div class="stat-desc">تومان</div>
        </div>
        <div class="stat">
            <div class="stat-title">هزینه</div>
            <div class="stat-value text-lg text-error">{{ to_persian_digits(number_format($expenses)) }}</div>
            <div class="stat-desc">تومان</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ $profit >= 0 ? 'سود خالص' : 'زیان خالص' }}</div>
            <div class="stat-value text-lg {{ $profit >= 0 ? 'text-success' : 'text-error' }}">
                {{ to_persian_digits(number_format(abs($profit))) }}
            </div>
            <div class="stat-desc">تومان</div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <x-card title="هزینه به تفکیک دسته">
            @forelse ($byCategory as $row)
                <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0 text-sm">
                    <span>
                        @if ($row['category'])
                            <span class="badge badge-xs {{ $row['category']->color() }}"></span>
                        @endif
                        {{ $row['label'] }}
                    </span>
                    <span class="font-medium">{{ to_persian_digits(number_format($row['total'])) }}</span>
                </div>
            @empty
                <p class="text-sm opacity-60">هزینه‌ای ثبت نشده است.</p>
            @endforelse
        </x-card>

        <x-card title="هزینهٔ ثابت در برابر متغیر">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span>ثابت (اجاره و حقوق)</span>
                    <span class="font-medium">{{ to_persian_digits(number_format($fixedVsVariable['fixed'])) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>متغیر</span>
                    <span class="font-medium">{{ to_persian_digits(number_format($fixedVsVariable['variable'])) }}</span>
                </div>
                <p class="text-xs opacity-60">
                    درآمد اینجا «وصولی» است نه «فاکتور صادرشده»؛ فروشِ نسخه‌ای که هنوز
                    پرداخت نشده سود نیست.
                </p>
            </div>
        </x-card>
    </div>

    <x-card title="روند ماهانه (شمسی)">
        @if ($monthly->isEmpty())
            <p class="text-sm opacity-60">داده‌ای برای نمایش نیست.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ماه</th>
                            <th>درآمد</th>
                            <th>هزینه</th>
                            <th>{{ $profit >= 0 ? 'سود' : 'زیان' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthly as $month)
                            <tr>
                                <td>{{ $month['label'] }}</td>
                                <td class="text-success">{{ to_persian_digits(number_format($month['income'])) }}</td>
                                <td class="text-error">{{ to_persian_digits(number_format($month['expenses'])) }}</td>
                                <td class="{{ $month['profit'] >= 0 ? '' : 'text-error' }}">
                                    {{ to_persian_digits(number_format($month['profit'])) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
