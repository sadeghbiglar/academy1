<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * یک هزینهٔ آموزشگاه.
 *
 * `spent_at` تاریخ وقوع است، نه تاریخ ثبت: قبض برق را ده روز بعد وارد
 * می‌کنند و باید در ماهی بنشیند که واقعاً در آن مصرف شده، وگرنه صورت سود
 * یک ماه را به‌هم می‌ریزد.
 */
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'amount',
        'notes',
        'spent_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'integer',
            'spent_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        ExpenseCategory $category,
        string $title,
        int $amount,
        string $spentAt,
        ?User $by = null,
        ?string $notes = null,
    ): self {
        return static::create([
            'user_id' => $by?->id ?? auth()->id(),
            'category' => $category,
            'title' => $title,
            'amount' => $amount,
            'spent_at' => $spentAt,
            'notes' => $notes,
        ]);
    }

    public function amountLabel(): string
    {
        return to_persian_digits(number_format($this->amount)).' تومان';
    }

    public function spentAtJalali(): string
    {
        return jalali_date($this->spent_at);
    }

    public function categoryLabel(): string
    {
        return $this->category?->label() ?? '—';
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            // هزینهٔ صفر یا منفی در گزارش سود یعنی سود ساختگی یا هزینهٔ منفی
            if ($expense->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'مبلغ هزینه باید بیشتر از صفر باشد.',
                ]);
            }
        });
    }
}
