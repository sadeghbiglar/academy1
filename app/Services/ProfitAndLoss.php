<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * صورت سود و زیان یک بازه.
 *
 * درآمد اینجا «وصولی» است، نه «فاکتور صادرشده». سودِ فروشِ نسخه‌ای که هنوز
 * پرداخت نشده سود نیست؛ این عدد همان است که حساب آموزشگاه را نشان می‌دهد و
 * همان چیزی است که مالیات و اجاره باید سنجیده شود با آن.
 */
class ProfitAndLoss
{
    /** وصولی یک بازه. */
    public function income(?string $from = null, ?string $to = null): int
    {
        return (int) $this->payments($from, $to)->sum('amount');
    }

    /** هزینه یک بازه. */
    public function expenses(?string $from = null, ?string $to = null): int
    {
        return (int) $this->expenseRows($from, $to)->sum('amount');
    }

    /** سود یا زیان خالص. منفی یعنی زیان. */
    public function profit(?string $from = null, ?string $to = null): int
    {
        return $this->income($from, $to) - $this->expenses($from, $to);
    }

    public function isProfit(?string $from = null, ?string $to = null): bool
    {
        return $this->profit($from, $to) >= 0;
    }

    /**
     * هزینه‌ها به تفکیک دسته، بزرگ‌ترین اول.
     *
     * @return Collection<int, array{category: ExpenseCategory|null, label: string, total: int}>
     */
    public function byCategory(?string $from = null, ?string $to = null): Collection
    {
        return $this->expenseRows($from, $to)
            ->groupBy(fn (Expense $expense) => $expense->category?->value)
            ->map(function (Collection $rows, string $key) {
                $category = ExpenseCategory::tryFrom($key);

                return [
                    'category' => $category,
                    'label' => $category?->label() ?? 'نامشخص',
                    'total' => (int) $rows->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /** تفکیک هزینه‌های ثابت از متغیر — ساختار سود به آن وابسته است. */
    public function fixedVsVariable(?string $from = null, ?string $to = null): array
    {
        $rows = $this->expenseRows($from, $to);

        $fixed = (int) $rows->filter(fn (Expense $e) => $e->category?->isFixed())->sum('amount');
        $variable = (int) $rows->reject(fn (Expense $e) => $e->category?->isFixed())->sum('amount');

        return ['fixed' => $fixed, 'variable' => $variable];
    }

    /**
     * درآمد و هزینهٔ هر ماه شمسی در یک بازه — برای نمودار.
     *
     * ماه‌های بدون فعالیت هم برگردانده می‌شوند با صفر. حذف ماه خالی یعنی نمودار
     * فاصلهٔ زمانی را پنهان می‌کند و «رشد» را بزرگ‌تر نشان می‌دهد.
     *
     * @return Collection<int, array{month: string, label: string, income: int, expenses: int, profit: int}>
     */
    public function monthly(?string $from = null, ?string $to = null): Collection
    {
        $months = $this->monthsBetween($from, $to);

        $incomeByMonth = $this->payments($from, $to)
            ->groupBy(fn (Payment $payment) => jalali_date($payment->paid_at, 'Y/m'))
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'));

        $expensesByMonth = $this->expenseRows($from, $to)
            ->groupBy(fn (Expense $expense) => jalali_date($expense->spent_at, 'Y/m'))
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'));

        return $months->map(function (string $month) use ($incomeByMonth, $expensesByMonth) {
            $income = (int) ($incomeByMonth[$month] ?? 0);
            $expenses = (int) ($expensesByMonth[$month] ?? 0);

            return [
                'month' => $month,
                'label' => to_persian_digits($month),
                'income' => $income,
                'expenses' => $expenses,
                'profit' => $income - $expenses,
            ];
        });
    }

    /**
     * فهرست ماه‌های شمسی بین دو تاریخ میلادی.
     *
     * @return Collection<int, string>
     */
    private function monthsBetween(?string $from, ?string $to): Collection
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : null;
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        // بدون تاریخ شروع، از اولین رکورد شروع می‌کنیم وگرنه نمودار شش ماه
        // عقب را خالی نشان می‌دهد
        $firstExpense = $this->expenseRows(null, null)->min('spent_at');
        $firstPayment = $this->payments(null, null)->min('paid_at');

        $earliest = collect([$firstExpense, $firstPayment])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->startOfDay())
            ->sort()
            ->first();

        $start = $start ?? $earliest;

        if ($start === null) {
            // هیچ داده‌ای نیست: فقط ماه جاری
            return collect([jalali_date(now(), 'Y/m')]);
        }

        // فهرست ماه‌های شمسیِ بازه با تکرار روی ماه‌های میلادی و یکتا کردن:
        // هر ماه میلادی دقیقاً یک ماه شمسی است و ماه‌های شمسی متوالی از ماه‌های
        // متوالی میلادی به دست می‌آیند، پس چیزی از قلم نمی‌افتد.
        //
        // حلقه روی خودِ Jalalian امن نبود: باید روز را به اول ماه قفل کرد و
        // Jalalian::day() هم وجود ندارد. این روش هرگز بی‌نهایت نمی‌شود.
        $months = collect();
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $months->push(jalali_date($cursor, 'Y/m'));
            $cursor->addMonthNoOverflow();
        }

        return $months->unique()->values();
    }

    /** @return Collection<int, Payment> */
    private function payments(?string $from, ?string $to): Collection
    {
        return Payment::query()
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->get();
    }

    /** @return Collection<int, Expense> */
    private function expenseRows(?string $from, ?string $to): Collection
    {
        return Expense::query()
            ->when($from, fn ($q) => $q->whereDate('spent_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('spent_at', '<=', $to))
            ->get();
    }
}
