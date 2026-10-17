<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return $this->due_at !== null
            && $this->due_at->isPast()
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
