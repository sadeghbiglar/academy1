<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * سرپرست دانش‌آموز.
 *
 * هر دانش‌آموز چند سرپرست دارد ولی فقط یکی «اصلی» است — همانی که به او زنگ
 * می‌زنند. این قاعده با ایندکس یکتا enforce نشد چون
 * unique(student_id, is_primary) بیش از حد سخت‌گیر است و حتی دو سرپرست
 * غیر‌اصلی را هم رد می‌کند؛ partial index هم بین sqlite و mysql یکسان رفتار
 * نمی‌کند. پس در مدل اعمال می‌شود.
 */
class Guardian extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'relation',
        'mobile',
        'phone',
        'email',
        'national_code',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * نسبت سرپرست به دانش‌آموز.
     *
     * متن آزاد است و در جدول مقدار دارد؛ نگاشت به فهرست ثابت یعنی هر
     * نسبت جدیدی یک مهاجرت دیتابیس می‌خواهد، در حالی که «عمو» هم یک نسبت
     * معتبر است.
     */
    public function relationLabel(): string
    {
        return $this->relation ?: '—';
    }

    protected static function booted(): void
    {
        // قبل از هر نوشتن، نه فقط هنگام درج
        static::saving(function (self $guardian) {
            if (! $guardian->is_primary) {
                return;
            }

            $duplicate = static::query()
                ->where('student_id', $guardian->student_id)
                ->where('is_primary', true)
                ->when($guardian->exists, fn ($query) => $query->whereKeyNot($guardian->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'is_primary' => 'این دانش‌آموز از قبل سرپرست اصلی دارد.',
                ]);
            }
        });
    }
}
