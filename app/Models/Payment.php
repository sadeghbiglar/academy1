<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * یک پرداخت روی یک فاکتور.
 *
 * پرداخت ویرایش نمی‌شود. اشتباه در پرداخت با یک سطر معکوس جبران می‌شود،
 * چون ویرایش یعنی از بین رفتن سابقهٔ «چه مبلغی کِی و چطور پرداخت شد» و
 * دفتر مالی بدون سابقه ارزش ندارد.
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'student_id',
        'user_id',
        'amount',
        'method',
        'reference',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'paid_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** کارمندی که پرداخت را ثبت کرده. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ثبت پرداخت روی فاکتور.
     *
     * مبلغ صفر رد می‌شود چون رکورد صفر یعنی ثبتی که فقط سطر اضافه می‌کند و
     * بعداً گزارش پرداخت‌ها را به هم می‌ریزد.
     */
    public static function pay(
        Invoice $invoice,
        int $amount,
        PaymentMethod $method = PaymentMethod::Cash,
        ?string $paidAt = null,
        ?string $reference = null,
        ?User $by = null,
        ?string $notes = null,
    ): self {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'مبلغ پرداخت باید بیشتر از صفر باشد.',
            ]);
        }

        if ($method->requiresReference() && blank($reference)) {
            throw ValidationException::withMessages([
                'reference' => 'برای این روش پرداخت، شمارهٔ پیگیری الزامی است.',
            ]);
        }

        $payment = static::create([
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'user_id' => $by?->id ?? auth()->id(),
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'paid_at' => $paidAt ?? now()->toDateString(),
            'notes' => $notes,
        ]);

        // وضعیت از روی همین پرداخت دوباره حساب می‌شود، نه حدسِ ثبت‌کننده
        $invoice->syncStatus()->save();

        return $payment;
    }

    /**
     * سطر معکوس برای برگرداندن یک پرداخت اشتباه.
     *
     * مبلغ منفی است و سطر اصلی دست‌نخورده می‌ماند؛ ویرایش سطر اصلی سابقه را
     * پاک می‌کرد و این تناقض دفتر مالی را از بین می‌برد.
     */
    public function reverse(?User $by = null, ?string $notes = null): self
    {
        $reversal = static::create([
            'invoice_id' => $this->invoice_id,
            'student_id' => $this->student_id,
            'user_id' => $by?->id ?? auth()->id(),
            'amount' => -$this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'paid_at' => now()->toDateString(),
            'notes' => $notes ?? 'برگشت پرداخت شمارهٔ '.$this->id,
        ]);

        // فاکتور باید دوباره حساب شود؛ بدون این، فاکتور «پرداخت جزئی» می‌ماند
        // در حالی که هیچ پولی ثبت نشده است
        $this->invoice->syncStatus()->save();

        return $reversal;
    }

    public function isReversal(): bool
    {
        return $this->amount < 0;
    }

    public function amountLabel(): string
    {
        return to_persian_digits(number_format(abs($this->amount))).' تومان';
    }

    public function methodLabel(): string
    {
        return $this->method?->label() ?? '—';
    }

    public function paidAtJalali(): ?string
    {
        return $this->paid_at ? jalali_date($this->paid_at) : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $payment) {
            // صفر و پرداخت‌های مثیفِ دستی، خطای ورودی‌اند. مبلغ منفی فقط از
            // مسیر reverse() می‌آید، پس کلاس خودش آن را می‌سازد.
            if ($payment->amount === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'مبلغ پرداخت نمی‌تواند صفر باشد.',
                ]);
            }
        });
    }
}
