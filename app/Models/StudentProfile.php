<?php

namespace App\Models;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * پروندهٔ آموزشی دانش‌آموز.
 *
 * جدا از خود Student است چون این داده‌ها مالکیت و سرنوشت متفاوتی دارند:
 * Student رکورد پایه و تقریباً تغییرناپذیر است (نام، کد ملی)، ولی وضعیت
 * پرونده هر ترم عوض می‌شود. یک جدول یکی می‌کرد هر بار به‌روزرسانی وضعیت را
 * با ویرایش ردیف دانش‌آموز قاطی می‌کرد.
 */
class StudentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'student_code',
        'status',
        'enrolled_at',
        'photo_path',
        'sheba',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'enrolled_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function statusColor(): string
    {
        return $this->status?->color() ?? 'badge-ghost';
    }

    /** کد دانش‌آموزی، یا «—» وقتی پرونده هنوز ثبت نشده. */
    public function codeLabel(): string
    {
        return $this->student_code ?: '—';
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }

    /** دانش‌آموزی که هنوز به پروندهٔ فعال نرسیده است. */
    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }
}
