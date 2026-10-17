@extends('layouts.academy')

@section('title', 'مالی و فاکتورها')

<div>
    <h1 class="text-2xl font-bold mb-4">مالی و فاکتورها</h1>

    {{-- خلاصه --}}
    <div class="stats stats-vertical sm:stats-horizontal shadow mb-6">
        <div class="stat">
            <div class="stat-title">معوق‌ها</div>
            <div class="stat-value text-lg text-error">
                {{ to_persian_digits(number_format($totalReceivable)) }}
            </div>
            <div class="stat-desc">تومان</div>
        </div>
        <div class="stat">
            <div class="stat-title">تعداد فاکتورها</div>
            <div class="stat-value text-lg">{{ to_persian_digits($invoices->total()) }}</div>
            <div class="stat-desc">در این صفحه</div>
        </div>
    </div>

    <form method="GET" action="{{ route('finance.index') }}" class="flex flex-wrap gap-2 mb-4">
        <input type="text" name="search" value="{{ $search }}" placeholder="نام، کد ملی یا شمارهٔ فاکتور"
               class="input input-bordered input-sm" />
        <select name="status" class="select select-bordered select-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach ($statuses as $option)
                <option value="{{ $option->value }}" @selected($status === $option)>
                    {{ $option->label() }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-primary">فیلتر</button>
        @if ($search || $status)
            <a href="{{ route('finance.index') }}" class="btn btn-sm btn-ghost">پاک کردن</a>
        @endif
    </form>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-base-100 rounded-box shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>شماره</th>
                        <th>دانش‌آموز</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>مانده</th>
                        <th>موعد</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="font-mono">{{ $invoice->number }}</td>
                            <td>{{ $invoice->student?->full_name ?? '—' }}</td>
                            <td>{{ $invoice->course?->title ?? '—' }}</td>
                            <td>{{ to_persian_digits(number_format($invoice->amount)) }}</td>
                            <td class="{{ $invoice->remaining() > 0 ? 'text-error' : '' }}">
                                {{ to_persian_digits(number_format($invoice->remaining())) }}
                            </td>
                            <td>{{ $invoice->dueAtJalali() ?? '—' }}</td>
                            <td>
                                <span class="badge badge-sm {{ $invoice->status->color() }}">
                                    {{ $invoice->statusLabel() }}
                                </span>
                            </td>
                            <td class="flex gap-1">
                                <a href="{{ route('finance.show', $invoice) }}" class="btn btn-xs">جزئیات</a>
                                <a href="{{ route('finance.invoice.print', $invoice) }}"
                                   class="btn btn-xs btn-ghost" target="_blank">چاپ</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center opacity-60 py-6">فاکتوری یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</div>
