<?php

namespace App\Models;

use App\Enums\DisciplineType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک رخداد انضباطی دانش‌آموز: تذکر، تقدیر، غیبت یا تأخیر.
 *
 * تاریخ در پایگاه میلادی است تا بازهٔ گزارش مرتب و قابل مقایسه بماند؛
 * نمایش در پرونده و گزارش فقط شمسی است (helpers.php، نه Jalalian در view).
 */
class Discipline extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'user_id',
        'type',
        'description',
        'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'type' => DisciplineType::class,
            'occurred_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** کسی که رخداد را ثبت کرده — با دانش‌آموز فرق دارد و نباید یکی شود. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return $this->type?->label() ?? '—';
    }

    public function typeColor(): string
    {
        return $this->type?->color() ?? 'badge-ghost';
    }

    /** تاریخ شمسی برای نمایش؛ در DB همیشه میلادی. */
    public function occurredOnJalali(): ?string
    {
        return $this->occurred_on ? jalali_date($this->occurred_on) : null;
    }

    /**
     * بازهٔ شامل (هر دو سر داخل بازه).
     *
     * سرها میلادی ISO‌اند — گزارش شمسی آن‌ها را با miladi_date تبدیل می‌کند و
     * این‌جا فقط مقایسهٔ تاریخ انجام می‌شود.
     *
     * سرِ بالا باید ' 23:59:59' بگیرد: ستون date در sqlite به‌شکل
     * «۲۰۲۶-۱۱-۳۰ ۰۰:۰۰:۰۰» ذخیره می‌شود و مقایسهٔ رشته‌ای با '2026-11-30'
     * آن را بیرون بازه می‌اندازد — یعنی روزِ آخر هر بازه بی‌صدا حذف می‌شود.
     */
    public function scopeInRange(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where('occurred_on', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('occurred_on', '<=', $to.' 23:59:59'));
    }

    /**
     * ثبت یک رخداد انضباطی.
     *
     * @param  string|null  $occurredOn  تاریخ میلادی ISO که picker ساخته است
     */
    public static function record(
        Student $student,
        DisciplineType $type,
        string $description,
        ?string $occurredOn = null,
        ?User $by = null,
    ): self {
        return static::create([
            'student_id' => $student->id,
            'user_id' => $by?->id ?? auth()->id(),
            'type' => $type,
            'description' => $description,
            'occurred_on' => $occurredOn ?? now()->toDateString(),
        ]);
    }
}
