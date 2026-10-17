<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'grade_level',
        'annual_hours',
        'description',
        'is_active',
    ];

    /**
     * پیش‌فرض‌های سمت مدل، نه فقط پایگاه داده.
     *
     * پایگاه داده برای مسیرهایی که از Eloquent نمی‌گذرند default دارد، ولی
     * شیء مدل بلافاصله بعد از create() این مقدار را نمی‌شناسد — و یک
     * `$subject->is_active` که null است یعنی یک شاخهٔ if که هرگز اجرا نمی‌شود.
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'grade_level' => 'integer',
            'annual_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * استادهایی که این درس را تدریس می‌کنند.
     *
     * معکوس تخصص‌های استاد: برای صفحهٔ انتخاب معلمِ یک درس، از این طرف پرس‌وجو
     * می‌شود نه آن یکی.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'teacher_subjects');
    }

    /** دروسی که هنوز هیچ استاد فعالی برایشان ثبت نشده. */
    public function scopeWithoutTeacher($query)
    {
        return $query->whereDoesntHave('teachers');
    }

    /**
     * ساعت باقی‌ماندهٔ سالانهٔ این درس.
     *
     * ظرفیت بر حسب ساعت است ولی هر دوره جلسه‌ها و مدتش جدا دارد، پس باید در
     * پایگاه داده ضرب شود و جمع گرفته شود — جمع سادهٔ session_count جواب
     * نمی‌دهد چون جلسات ۹۰ دقیقه‌ای و ۱۲۰ دقیقه‌ای را یکی می‌شمارد.
     *
     * دورهٔ بایگانی‌شده حساب نمی‌شود: سالش تمام شده و نباید ظرفیت سال بعد را
     * بخورد.
     */
    public function remainingHours(): ?int
    {
        if ($this->annual_hours === null) {
            return null;
        }

        // alias لازم است: value() روی اولین ستون کار می‌کند و بدون نام، مقدار
        // session_count را برمی‌گرداند نه جمع را
        $used = (float) $this->courses()
            ->where('status', '!=', CourseStatus::Archived->value)
            ->selectRaw('COALESCE(SUM(session_count * session_duration), 0) / 60 as used_hours')
            ->value('used_hours');

        return (int) round($this->annual_hours - $used);
    }

    /** آیا این درس از ظرفیت سالانه‌اش گذشته است؟ */
    public function isOverCapacity(): bool
    {
        return ($this->remainingHours() ?? PHP_INT_MAX) < 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
