<?php

namespace App\Enums;

/**
 * وضعیت فاکتور شهریه.
 *
 * تفکیک «پرداخت‌شده» و «تسویه‌شده» عمدی است: پرداخت‌شده یعنی پول دریافت شد،
 * تسویه‌شده یعنی کل بدهی بسته شد. یک فاکتور می‌تواند پرداخت‌شده باشد ولی هنوز
 * کسری داشته باشد؛ اگر این دو یکی شوند، وضعیت فاکتور با وضعیت حساب دانش‌آموز
 * ناسازگار می‌شود.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';           // پیش‌نویس، قابل حذف
    case Issued = 'issued';         // صادر شده، منتظر پرداخت
    case Partial = 'partial';       // پرداخت جزئی
    case Paid = 'paid';             // کامل پرداخت شد
    case Overdue = 'overdue';       // گذشته از مهلت
    case Cancelled = 'cancelled';   // لغو شده

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Issued => 'صادر شده',
            self::Partial => 'پرداخت جزئی',
            self::Paid => 'پرداخت شده',
            self::Overdue => 'معوق',
            self::Cancelled => 'لغو شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'badge-ghost',
            self::Issued, self::Partial => 'badge-info',
            self::Paid => 'badge-success',
            self::Overdue => 'badge-error',
            self::Cancelled => 'badge-neutral',
        };
    }

    /** آیا در مبلغ حساب (بستانکاری) می‌آید؟ */
    public function countsAsReceivable(): bool
    {
        return ! in_array($this, [self::Draft, self::Cancelled, self::Paid], true);
    }

    /** آیا هنوز قابل ویرایش است؟ */
    public function editable(): bool
    {
        return $this === self::Draft;
    }

    /** فاکتور پرداخت‌شده را نمی‌شود لغو یا حذف کرد؛ پولش رفته. */
    public function deletable(): bool
    {
        return in_array($this, [self::Draft, self::Cancelled], true);
    }

    /** پرداخت روی فاکتور پیش‌نویس معنا ندارد. */
    public function acceptsPayment(): bool
    {
        return in_array($this, [self::Issued, self::Partial, self::Overdue], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['id' => $s->value, 'name' => $s->label()], self::cases());
    }

    public static function labelFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        return is_scalar($value) ? (self::tryFrom((string) $value)?->label() ?? '—') : '—';
    }
}
