<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * یک قسط از شهریه.
 *
 * مبلغ و تاریخ سررسید عمداً کپیِ فاکتور نیستند. اگر مبلغ قسط از فاکتور محاسبه
 * می‌شد، هر تغییر تخفیف فاکتور برنامهٔ قسطی را که با خانواده توافق شده عوض
 * می‌کرد — و مبلغی که قرار بود سر بیستم پرداخت شود ناگهان به شانزدهم می‌افتاد.
 */
class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'sequence',
        'amount',
        'due_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * ساخت برنامهٔ اقساط.
     *
     * مبلغ‌ها جمع‌شان دقیقاً برابر مبلغ قابل پرداخت فاکتور است. تقسیم مساوی
     * معمولاً باقی‌مانده دارد و آن باقی‌مانده را در قسط آخر می‌گذاریم، چون
     * «سه قسط یک‌میلیون» با «سه قسط ۹۹۰ هزار» فرق دارد و اختلاف را باید
     * یک‌جا گرفت.
     *
     * @param  array<int, string>  $dueDates  تاریخ سررسیدها (میلادی ISO)
     */
    public static function plan(Invoice $invoice, array $dueDates, ?User $by = null): self
    {
        if ($invoice->isPaid()) {
            throw ValidationException::withMessages([
                'invoice' => 'فاکتور تسویه‌شده قابل قسط‌بندی نیست.',
            ]);
        }

        $count = count($dueDates);

        if ($count < 2) {
            throw ValidationException::withMessages([
                'due_at' => 'قسط‌بندی حداقل به دو قسط نیاز دارد.',
            ]);
        }

        $payable = (int) $invoice->payable;
        $each = intdiv($payable, $count);
        $remainder = $payable - $each * $count;

        $rows = [];

        foreach (array_values($dueDates) as $index => $dueAt) {
            $rows[] = [
                'invoice_id' => $invoice->id,
                // باقی‌مانده در قسط آخر: جمع اقساط باید دقیقاً برابر مبلغ شود
                'sequence' => $index + 1,
                'amount' => $index === $count - 1 ? $each + $remainder : $each,
                'due_at' => $dueAt,
                'user_id' => $by?->id ?? auth()->id(),
            ];
        }

        // همهٔ سطرها با هم: plan() یک برنامه می‌سازد، نه یک قسط. نسخهٔ اول
        // فقط rows[0] را ذخیره می‌کرد و مبلغ هرگز شکسته نمی‌شد
        static::insert($rows);

        return static::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('sequence')
            ->firstOrFail();
    }

    /** پاک کردن کل برنامهٔ قسط یک فاکتور و ساخت دوبارهٔ آن. */
    public static function replan(Invoice $invoice, array $dueDates, ?User $by = null): self
    {
        $invoice->installments()->delete();

        return static::plan($invoice, $dueDates, $by);
    }

    /** برنامهٔ اقساط یک فاکتور، به ترتیب سررسید. */
    public static function planOf(Invoice $invoice)
    {
        return $invoice->installments()->orderBy('sequence')->get();
    }

    public function isDue(): bool
    {
        // مقایسهٔ تاریخ، نه ساعت: cast روی date نیمه‌شب همان روز می‌نشیند و
        // مقایسهٔ زمانی، سررسیدِ امروز را «گذشته» می‌گیرد
        return ! $this->due_at->isFuture();
    }

    public function isOverdue(): bool
    {
        // lt با تاریخ امروز، نه با لحظهٔ فعلی: cast روی date نیمه‌شب می‌دهد
        // و مقایسهٔ لحظه‌ای سررسیدِ امروز را گذشته می‌شمرد
        return $this->due_at->lt(today());
    }

    public function amountLabel(): string
    {
        return to_persian_digits(number_format($this->amount)).' تومان';
    }

    public function dueAtJalali(): string
    {
        return jalali_date($this->due_at);
    }
}
