@extends('layouts.academy')

@section('title', 'فاکتور '.$invoice->number)

<div>
    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('finance.index') }}" class="btn btn-sm btn-ghost">بازگشت به فاکتورها</a>
        <span class="text-sm opacity-60">{{ $invoice->number }}</span>
        <a href="{{ route('finance.invoice.print', $invoice) }}" class="btn btn-sm" target="_blank">
            فاکتور چاپی
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error mb-4">{{ session('error') }}</div>
    @endif

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-base-100 rounded-box shadow p-4">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold">{{ $invoice->student?->full_name }}</h2>
                        <p class="text-sm opacity-70">
                            {{ $invoice->course?->title ?? 'بدون دوره' }}
                        </p>
                    </div>
                    <span class="badge {{ $invoice->status->color() }}">{{ $invoice->statusLabel() }}</span>
                </div>

                <div class="divider"></div>

                <dl class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                    <div>
                        <dt class="opacity-60">مبلغ</dt>
                        <dd class="font-medium">{{ $invoice->amountLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="opacity-60">تخفیف</dt>
                        <dd class="font-medium">
                            {{ $invoice->discountLabel() ? to_persian_digits(number_format($invoice->discount_amount)) : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="opacity-60">قابل پرداخت</dt>
                        <dd class="font-medium">{{ to_persian_digits(number_format($invoice->payable)) }} تومان</dd>
                    </div>
                    <div>
                        <dt class="opacity-60">پرداخت‌شده</dt>
                        <dd class="font-medium">{{ to_persian_digits(number_format($invoice->paidAmount())) }} تومان</dd>
                    </div>
                    <div>
                        <dt class="opacity-60">مانده</dt>
                        <dd class="font-medium {{ $invoice->remaining() > 0 ? 'text-error' : '' }}">
                            {{ $invoice->payableLabel() }}
                        </dd>
                    </div>
                    <div>
                        <dt class="opacity-60">موعد</dt>
                        <dd class="font-medium">{{ $invoice->dueAtJalali() ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($invoice->overdueAmount() > 0)
                    <div class="alert alert-warning mt-4">
                        معوق: {{ to_persian_digits(number_format($invoice->overdueAmount())) }} تومان
                    </div>
                @endif
            </div>

            <div class="bg-base-100 rounded-box shadow p-4">
                <h3 class="font-bold mb-3">سوابق پرداخت</h3>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>مبلغ</th>
                            <th>روش</th>
                            <th>تاریخ</th>
                            <th>پیگیری</th>
                            <th>ثبت‌کننده</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoice->payments->sortByDesc('paid_at') as $payment)
                            <tr class="{{ $payment->isReversal() ? 'opacity-60' : '' }}">
                                <td class="{{ $payment->isReversal() ? 'line-through' : '' }}">
                                    {{ $payment->amountLabel() }}
                                </td>
                                <td>{{ $payment->methodLabel() }}</td>
                                <td>{{ $payment->paidAtJalali() }}</td>
                                <td>{{ $payment->reference ?? '—' }}</td>
                                <td>{{ $payment->user?->display_name ?? '—' }}</td>
                                <td>
                                    @if (! $payment->isReversal())
                                        <a href="{{ route('finance.receipt.print', $payment) }}"
                                           class="btn btn-xs btn-ghost" target="_blank">رسید</a>
                                    @else
                                        <span class="badge badge-ghost badge-xs">برگشت</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center opacity-60 py-4">پرداختی ثبت نشده است.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoice->hasInstallmentPlan())
                <div class="bg-base-100 rounded-box shadow p-4">
                    <h3 class="font-bold mb-3">برنامهٔ اقساط</h3>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>قسط</th>
                                <th>مبلغ</th>
                                <th>سررسید</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->installments()->orderBy('sequence')->get() as $installment)
                                <tr>
                                    <td>{{ to_persian_digits($installment->sequence) }}</td>
                                    <td>{{ $installment->amountLabel() }}</td>
                                    <td>{{ $installment->dueAtJalali() }}</td>
                                    <td>
                                        @if ($installment->isOverdue())
                                            <span class="badge badge-error badge-sm">معوق</span>
                                        @elseif ($installment->isDue())
                                            <span class="badge badge-warning badge-sm">سررسید امروز</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">در انتظار</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @if ($invoice->status->acceptsPayment())
                <form method="POST" action="{{ route('finance.payments.store', $invoice) }}"
                      class="bg-base-100 rounded-box shadow p-4 space-y-3">
                    @csrf
                    <h3 class="font-bold">ثبت پرداخت</h3>

                    <label class="form-control w-full">
                        <span class="label-text mb-1">مبلغ (تومان)</span>
                        <input type="number" name="amount" min="1" step="1" required
                               value="{{ $invoice->remaining() > 0 ? $invoice->remaining() : '' }}"
                               class="input input-bordered input-sm w-full" />
                    </label>

                    <label class="form-control w-full">
                        <span class="label-text mb-1">روش پرداخت</span>
                        <select name="method" class="select select-bordered select-sm w-full">
                            @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="form-control w-full">
                        <span class="label-text mb-1">تاریخ پرداخت (میلادی)</span>
                        <input type="date" name="paid_at" value="{{ today()->toDateString() }}"
                               class="input input-bordered input-sm w-full" />
                    </label>

                    <label class="form-control w-full">
                        <span class="label-text mb-1">شمارهٔ پیگیری</span>
                        <input type="text" name="reference" class="input input-bordered input-sm w-full" />
                        <span class="label-text-alt opacity-60">برای کارت و چک الزامی است.</span>
                    </label>

                    <button type="submit" class="btn btn-primary btn-sm w-full">ثبت پرداخت</button>
                </form>
            @else
                <div class="alert alert-info">
                    این فاکتور در وضعیت «{{ $invoice->statusLabel() }}» پرداخت نمی‌پذیرد.
                </div>
            @endif
        </div>
    </div>
</div>
