<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * فاکتور شهریه.
 *
 * `payable` عمداً ذخیره می‌شود و هر بار از تخفیف حساب نمی‌شود: اگر مدیر بعداً
 * تخفیف را عوض کند، مبلغی که خانواده قبلاً پرداخت کرده نباید جابه‌جا شود.
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'student_id',
        'course_id',
        'status',
        'amount',
        'discount_amount',
        'discount_type',
        'discount_value',
        'payable',
        'notes',
        'issued_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'discount_type' => DiscountType::class,
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'discount_value' => 'integer',
            'payable' => 'integer',
            'issued_at' => 'date',
            'due_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * اقساط، به ترتیب سررسید.
     *
     * ساخت برنامه در Installment::plan است، نه اینجا: این فقط خواندن است.
     */
    public function installmentsSorted(): HasMany
    {
        return $this->installments()->orderBy('sequence');
    }

    /** جمع کل اقساط. باید برابر مبلغ قابل پرداخت باشد. */
    public function plannedAmount(): int
    {
        return (int) $this->installments()->sum('amount');
    }

    public function hasInstallmentPlan(): bool
    {
        return $this->installments()->exists();
    }

    /**
     * اقساط معوق: سررسید گذشته و تا این لحظه پوشش داده نشده.
     *
     * پرداخت‌ها به ترتیب سررسید تخصیص می‌یابند، نه به ترتیب ثبت: خانواده معمولاً
     * پول را می‌دهد و می‌گوید «برای قسط بعدی»، و اگر پول بی‌ترتیب تخصیص یابد،
     * قسط اول معوق می‌ماند در حالی که قسط دوم پوشش‌داده‌شده نشان داده می‌شود.
     *
     * اعتبارِ بیش از حد فاکتور هم اقساط بعدی را می‌پوشاند؛ خانواده‌ای که کل
     * سال را یک‌جا داده، بدهکارِ معوق نیست.
     *
     * @return Collection<int, Installment>
     */
    public function overdueInstallments()
    {
        $installments = $this->installments()
            ->orderBy('sequence')
            ->whereDate('due_at', '<', today())
            ->get();

        $budget = $this->paidAmount();

        return $installments
            ->reject(function (Installment $installment) use (&$budget) {
                if ($budget >= $installment->amount) {
                    $budget -= $installment->amount;

                    return true;
                }

                return false;
            })
            ->values();
    }

    /** مجموع اقساط معوق؛ عددی که روی فاکتور نمایش داده می‌شود. */
    public function overdueAmount(): int
    {
        return (int) $this->overdueInstallments()->sum('amount');
    }

    // ── ساخت ───────────────────────────────────────────────

    /**
     * صدور فاکتور با شمارهٔ یکتا.
     *
     * شماره در ساخت همان لحظه ساخته می‌شود چون تکراری بودنش باید همان‌جا
     * خطا بدهد؛ شماره‌سازی بعد از ذخیره یعنی دو فاکتور هم‌زمان یک شماره.
     */
    public static function issue(
        Student $student,
        ?Course $course,
        int $amount,
        ?DiscountType $discountType = null,
        ?int $discountValue = null,
        ?string $dueAt = null,
        ?string $notes = null,
    ): self {
        if ($amount < 0) {
            throw ValidationException::withMessages([
                'amount' => 'مبلغ فاکتور نمی‌تواند منفی باشد.',
            ]);
        }

        $discount = self::computeDiscount($discountType, $discountValue, $amount);

        return static::create([
            'number' => self::nextNumber(),
            'student_id' => $student->id,
            'course_id' => $course?->id,
            'status' => InvoiceStatus::Issued,
            'amount' => $amount,
            'discount_amount' => $discount,
            'discount_type' => $discountType,
            'discount_value' => $discountType ? $discountValue : null,
            'payable' => $amount - $discount,
            'notes' => $notes,
            'issued_at' => now()->toDateString(),
            'due_at' => $dueAt,
        ]);
    }

    /**
     * صدور فاکتور با چند قاعدهٔ تخفیف.
     *
     * تخفیف‌ها ترکیب می‌شوند و مبلغ نهایی روی فاکتور ثابت می‌ماند. ریزِ هر
     * تخفیف جدا ذخیره نمی‌شود چون نوع/مقدارِ تخفیفِ اول و مجموعش همان اطلاعات
     * لازم برای چاپ است، و نگهداری «تخفیف ۱۰٪ + ۲۰۰ هزار» روی فاکتور به
     * معنی بازمحاسبه‌کردن چیزی است که باید تاریخ خودش را ثابت نگه دارد.
     *
     * @param  iterable<Discount>  $discounts
     */
    public static function issueWithDiscounts(
        Student $student,
        ?Course $course,
        int $amount,
        iterable $discounts,
        ?string $dueAt = null,
        ?string $notes = null,
    ): self {
        $list = collect($discounts);

        if ($list->isEmpty()) {
            return static::issue($student, $course, $amount, dueAt: $dueAt, notes: $notes);
        }

        // مبلغ تخفیفِ ترکیبی مستقیم ذخیره می‌شود، نه «نوع و مقدارِ اول».
        // عبور دادن ترکیب از مسیر percent/fixed باعث می‌شد «۱۰٪ و بعد
        // ۲۰۰ هزار» در فاکتور چاپی به یک تخفیف ۱۰٪ تقلیل پیدا کند.
        $total = Discount::combinedAmount($list, $amount);
        $allPercent = $list->every(fn (Discount $d) => $d->type === DiscountType::Percent);

        return static::create([
            'number' => static::nextNumber(),
            'student_id' => $student->id,
            'course_id' => $course?->id,
            'status' => InvoiceStatus::Issued,
            'amount' => $amount,
            'discount_amount' => $total,
            // ترکیبِ درصدی با مبلغ ثابت، در نمایش به مبلغ ثابت تبدیل می‌شود
            // تا چاپ با حساب واقعی یکی باشد
            'discount_type' => $allPercent ? DiscountType::Percent : DiscountType::Fixed,
            'discount_value' => $allPercent
                ? (int) $list->first()->value
                : $total,
            'payable' => $amount - $total,
            'notes' => $notes,
            'issued_at' => now()->toDateString(),
            'due_at' => $dueAt,
        ]);
    }

    /** IN-۱۲۳۴ — پیشوند نام آموزشگاه، بدون سال چون شماره باید یکتا بماند. */
    public static function nextNumber(): string
    {
        $next = (int) (static::query()->max('id') ?? 0) + 1;

        return 'IN-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /** تخفیف، محدود به خود مبلغ. */
    public static function computeDiscount(?DiscountType $type, ?int $value, int $amount): int
    {
        if ($type === null || $value === null || $value <= 0) {
            return 0;
        }

        return $type->applyTo($amount, $value);
    }

    // ── وضعیت ──────────────────────────────────────────────

    /** مجموعه پرداخت‌های ثبت‌شده روی این فاکتور. */
    public function paidAmount(): int
    {
        // می‌تواند منفی باشد: سطر برگشت پرداخت اشتباه را کم می‌کند
        return (int) $this->payments()->sum('amount');
    }

    /** مبلغ پرداختی فراتر از بدهی؛ طلب آموزشگاه از دانش‌آموز. */
    public function credit(): int
    {
        return max(0, $this->paidAmount() - (int) $this->payable);
    }

    public function remaining(): int
    {
        // max(0) چون پرداخت بیش از بدهی فاکتور را «بدهکار» نمی‌کند
        return max(0, (int) $this->payable - $this->paidAmount());
    }

    public function isPaid(): bool
    {
        return $this->remaining() === 0 && (int) $this->payable > 0;
    }

    public function isPartial(): bool
    {
        return $this->paidAmount() > 0 && $this->remaining() > 0;
    }

    /** فاکتور گذشته از مهلت و هنوز پرداخت‌نشده. */
    public function isOverdue(): bool
    {
        // مقایسه با تاریخ، نه با لحظه: cast روی date نیمه‌شب می‌دهد و
        // مقایسهٔ زمانی، موعدِ امروز را از همان صبح «گذشته» می‌گیرد
        return $this->due_at !== null
            && $this->due_at->lt(today())
            && $this->remaining() > 0
            && $this->status->acceptsPayment();
    }

    /**
     * همگام‌سازی وضعیت با پرداخت‌های واقعی.
     *
     * وضعیت از روی پرداخت‌ها محاسبه می‌شود، نه دستی: دو نفر که هم‌زمان فاکتور را
     * می‌بینند نباید بتوانند با یک پرداخت ناقص، «پرداخت‌شده» ثبت کنند.
     */
    public function syncStatus(): self
    {
        if ($this->isPaid()) {
            $this->status = InvoiceStatus::Paid;
        } elseif ($this->isPartial()) {
            $this->status = InvoiceStatus::Partial;
        } elseif ($this->isOverdue()) {
            $this->status = InvoiceStatus::Overdue;
        } elseif ($this->status === InvoiceStatus::Draft) {
            // پیش‌نویس با پیش‌نویس می‌ماند
        } else {
            $this->status = InvoiceStatus::Issued;
        }

        return $this;
    }

    // ── نمایش ──────────────────────────────────────────────

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    public function amountLabel(): string
    {
        return to_persian_digits(number_format($this->amount)).' تومان';
    }

    public function discountLabel(): ?string
    {
        if (! $this->discount_type || ! $this->discount_value) {
            return null;
        }

        return $this->discount_type === DiscountType::Percent
            ? to_persian_digits(number_format($this->discount_value)).'٪'
            : to_persian_digits(number_format($this->discount_value)).' تومان';
    }

    public function payableLabel(): string
    {
        return to_persian_digits(number_format($this->remaining())).' تومان';
    }

    public function dueAtJalali(): ?string
    {
        return $this->due_at ? jalali_date($this->due_at) : null;
    }

    public function issuedAtJalali(): ?string
    {
        return $this->issued_at ? jalali_date($this->issued_at) : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice) {
            if ($invoice->discount_type === null) {
                $invoice->discount_value = null;
                $invoice->discount_amount = 0;
            }

            $invoice->payable = (int) $invoice->amount - (int) $invoice->discount_amount;
        });
    }
}
