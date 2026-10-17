<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * گزارش وصولی و بدهکاران.
 *
 * وضعیت هر دانش‌آموز در یک بازهٔ تاریخ: چقدر فاکتور صادر شده، چقدر پرداخت
 * شده، مانده چقدر است و آخرین پرداختش کِی بوده.
 *
 * فیلتر بازه روی «تاریخ فاکتور» و «تاریخ پرداخت» جدا اعمال می‌شود، نه روی هم.
 * اگر هر دو روی یک ستون فیلتر شوند، پرداختِ یک فاکتورِ سال قبل که امسال
 * واریز شده از حساب می‌افتد و ماندهٔ دانش‌آموز غلط نشان داده می‌شود.
 */
class BalanceReport
{
    /**
     * وضعیت حساب یک دانش‌آموز.
     *
     * @return array{
     *     student: Student,
     *     total: int,
     *     paid: int,
     *     outstanding: int,
     *     credit: int,
     *     last_paid_at: Carbon|null,
     *     status: string
     * }
     */
    public function rowFor(Student $student, ?string $from = null, ?string $to = null): array
    {
        $invoices = Invoice::query()
            ->where('student_id', $student->id)
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value])
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Overdue->value,
                InvoiceStatus::Draft->value,
            ])
            ->when($from, fn ($q) => $q->whereDate('issued_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('issued_at', '<=', $to));

        $total = (int) $invoices->sum('payable');

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to));

        $paid = (int) $payments->sum('amount');

        $last = Payment::query()
            ->where('student_id', $student->id)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->max('paid_at');

        $outstanding = max(0, $total - $paid);

        return [
            'student' => $student,
            'total' => $total,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'credit' => max(0, $paid - $total),
            'last_paid_at' => $last ? Carbon::parse($last) : null,
            'status' => $this->statusLabel($outstanding, $paid, $total),
        ];
    }

    /**
     * وضعیت یک ردیف.
     *
     * «تسویه» وقتی است که پولی جابه‌جا شده و بدهی‌ای نمانده. دانش‌آموزی که
     * هیچ فاکتوری ندارد «تسویه» نیست: هیچ ادعایی وجود ندارد.
     */
    public function statusLabel(int $outstanding, int $paid, int $total): string
    {
        if ($total === 0 && $paid === 0) {
            return 'بدون حساب';
        }

        if ($outstanding === 0) {
            return 'تسویه';
        }

        return $paid > 0 ? 'نیمه‌پرداخت' : 'بدهکار';
    }

    public function statusColor(string $label): string
    {
        return match ($label) {
            'تسویه' => 'badge-success',
            'نیمه‌پرداخت' => 'badge-warning',
            'بدهکار' => 'badge-error',
            default => 'badge-ghost',
        };
    }

    /**
     * کل گزارش، مرتب‌شده بر اساس بزرگ‌ترین بدهکار.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array>
     */
    public function build($students, ?string $from = null, ?string $to = null): Collection
    {
        return $students
            ->map(fn (Student $student) => $this->rowFor($student, $from, $to))
            ->sortByDesc(fn (array $row) => [$row['outstanding'], $row['total']])
            ->values();
    }

    /** جمع ستون‌های یک بازه؛ جمع بدهی بزرگ‌تر، جمع کل دوم. */
    public function totals(Collection $rows): array
    {
        return [
            'total' => (int) $rows->sum('total'),
            'paid' => (int) $rows->sum('paid'),
            'outstanding' => (int) $rows->sum('outstanding'),
        ];
    }

    /**
     * وصولی‌های یک بازه، به تفکیک روش پرداخت.
     *
     * گزارش روش پرداخت بدون بازه بی‌معناست: همیشه بیشترین روزهای سال را
     * نشان می‌دهد.
     *
     * @return array<string, int>
     */
    public function collectedByMethod(?string $from = null, ?string $to = null): array
    {
        return Payment::query()
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * ماندهٔ کل آموزشگاه در یک بازه.
     *
     * فاکتور به فاکتور حساب می‌شود، نه «مجموع فاکتورها منهای مجموع پرداخت‌ها».
     * کسرِ کلی یعنی پرداختِ یک فاکتور پرداخت‌شده یا لغوشده، بدهی فاکتورِ
     * بازِ دیگری را هم پاک می‌کند و مانده کمتر از واقع نشان داده می‌شود.
     */
    public function totalOutstanding(?string $from = null, ?string $to = null): int
    {
        $invoices = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Overdue->value,
            ])
            ->when($from, fn ($q) => $q->whereDate('issued_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('issued_at', '<=', $to))
            ->get();

        if ($invoices->isEmpty()) {
            return 0;
        }

        $paidPerInvoice = Payment::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id')
            ->map(fn ($total) => (int) $total);

        // کلیدهای pluck می‌توانند رشته باشند؛ با intval نرمال می‌شوند وگرنه
        // «Undefined array key» برای فاکتوری که پرداخت ندارد
        $ids = $invoices->map(fn (Invoice $invoice) => $invoice->id)->all();
        $lookup = [];

        foreach ($ids as $id) {
            $lookup[(int) $id] = (int) ($paidPerInvoice[(int) $id] ?? 0);
        }

        return (int) $invoices->sum(function (Invoice $invoice) use ($lookup) {
            // هر فاکتور مستقل: پولِ فاکتورِ دیگر بدهیِ این را نمی‌پوشاند
            return max(0, (int) $invoice->payable - $lookup[(int) $invoice->id]);
        });
    }
}
