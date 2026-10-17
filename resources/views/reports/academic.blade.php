@extends('layouts.academy')

@section('title', 'گزارش‌های آموزشی')

<div>
    <h1 class="text-2xl font-bold mb-4">گزارش‌های آموزشی</h1>

    <form method="GET" action="{{ route('reports.academic') }}"
          class="bg-base-100 rounded-box shadow p-4 mb-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
        <label class="form-control">
            <span class="label-text mb-1">از تاریخ</span>
            <input type="text" name="from" value="{{ $from }}" placeholder="۱۴۰۵/۰۷/۰۱"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>
        <label class="form-control">
            <span class="label-text mb-1">تا تاریخ</span>
            <input type="text" name="to" value="{{ $to }}" placeholder="۱۴۰۵/۰۷/۳۰"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>
        <label class="form-control">
            <span class="label-text mb-1">مقایسه با: از</span>
            <input type="text" name="previous_from" value="{{ $previousFrom }}" placeholder="۱۴۰۵/۰۴/۰۱"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>
        <label class="form-control">
            <span class="label-text mb-1">مقایسه با: تا</span>
            <input type="text" name="previous_to" value="{{ $previousTo }}" placeholder="۱۴۰۵/۰۶/۳۱"
                   class="input input-bordered input-sm" dir="ltr" />
        </label>
        <div class="lg:col-span-4 flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary">اعمال</button>
            @if ($from || $to || $previousFrom || $previousTo)
                <a href="{{ route('reports.academic') }}" class="btn btn-sm btn-ghost">حذف فیلتر</a>
            @endif
        </div>
    </form>

    {{-- جلسات برگزارشده: مخرج واقعی درصد حضور --}}
    <div class="stats stats-vertical sm:stats-horizontal shadow mb-6">
        <div class="stat">
            <div class="stat-title">جلسات برگزارشده</div>
            <div class="stat-value text-lg">{{ to_persian_digits($sessions->count()) }}</div>
            <div class="stat-desc">در این بازه</div>
        </div>
        <div class="stat">
            <div class="stat-title">دانش‌آموزان کم‌حضور</div>
            <div class="stat-value text-lg text-error">{{ to_persian_digits($lowAttendance->count()) }}</div>
            <div class="stat-desc">زیر آستانه</div>
        </div>
    </div>

    {{-- فهرست کم‌حضورها --}}
    <x-card title="دانش‌آموزان کم‌حضور" class="mb-6">
        @forelse ($lowAttendance as $row)
            <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0 text-sm">
                <span>{{ $row['student']->full_name }}</span>
                <div class="flex items-center gap-2">
                    <span class="font-medium">
                        {{ to_persian_digits(number_format($row['attendance']['rate'], 1)) }}٪
                    </span>
                    <span class="badge badge-sm {{ $row['level'] === 'critical' ? 'badge-error' : 'badge-warning' }}">
                        {{ $row['level'] === 'critical' ? 'غیبت بیش از حد' : 'زیر آستانه' }}
                    </span>
                </div>
            </div>
        @empty
            <p class="text-sm opacity-60">دانش‌آموز کم‌حضوری در این بازه نیست.</p>
        @endforelse
    </x-card>

    {{-- جدول اصلی --}}
    <div class="bg-base-100 rounded-box shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>دانش‌آموز</th>
                        <th>درصد حضور</th>
                        <th>غیبت</th>
                        <th>موجه</th>
                        <th>میانگین نمرات</th>
                        <th>مقایسه با بازهٔ قبل</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['student']->full_name }}</td>
                            <td>
                                @if ($row['attendance']['rate'] === null)
                                    <span class="opacity-60">—</span>
                                @else
                                    {{ to_persian_digits(number_format($row['attendance']['rate'], 1)) }}٪
                                @endif
                            </td>
                            <td class="{{ $row['attendance']['absent'] > 0 ? 'text-error' : '' }}">
                                {{ to_persian_digits($row['attendance']['absent']) }}
                            </td>
                            <td>{{ to_persian_digits($row['attendance']['excused']) }}</td>
                            <td>
                                {{ $row['average'] === null ? '—' : to_persian_digits(number_format($row['average'], 1)) }}
                            </td>
                            <td>
                                @if ($progress !== null && isset($progress[$row['student']->id]))
                                    <span class="text-xs">
                                        حضور: {{ $progress[$row['student']->id]['attendance_trend'] }}
                                        — نمره: {{ $progress[$row['student']->id]['score_trend'] }}
                                    </span>
                                @else
                                    <span class="opacity-60 text-xs">بازهٔ قبل تعیین نشده</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center opacity-60 py-6">دانش‌آموزی ثبت نشده است.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
