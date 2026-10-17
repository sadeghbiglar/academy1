<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'national_code',
        'father_name',
        'birth_date',
        'gender',
        'mobile',
        'phone',
        'email',
        'subject',
        'hire_date',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'gender' => Gender::class,
        ];
    }

    public function genderLabel(): string
    {
        return $this->gender?->label() ?? '—';
    }

    /**
     * نیم‌رخ پرسنلی.
     *
     * یک‌به‌یک و نه hasMany: دو نیم‌رخ برای یک استاد یعنی دو شمارهٔ بیمه و دو
     * حقوق، پس یکتایی را خود پایگاه داده enforce می‌کند.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(TeacherExperience::class)->orderByDesc('started_at');
    }

    /** درس‌هایی که این استاد می‌تواند تدریس کند. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** آخرین سابقهٔ شروع‌شده — همان چیزی که روی کارت استاد «سابقهٔ فعلی» است. */
    public function currentExperience(): ?TeacherExperience
    {
        return $this->experiences->first();
    }

    public function statusLabel(): string
    {
        return $this->profile?->statusLabel() ?? '—';
    }

    /** نام درس‌های تخصص، برای نمایش در یک سلول جدول. */
    public function specialtyNames(): array
    {
        return $this->subjects->pluck('name')->all();
    }

    public function specialtyLabel(): string
    {
        return $this->specialtyNames() === [] ? '—' : implode('، ', $this->specialtyNames());
    }

    /** خلاصهٔ سوابق برای نمایش فشرده؛ رشتهٔ خالی یعنی «سابقه‌ای ثبت نشده». */
    public function experiencesLabel(): string
    {
        $experiences = $this->experiences;

        return $experiences->isEmpty() ? '—' : $experiences->map->label()->implode('، ');
    }

    /** استادی که هم وضعیت فعال دارد و برای این درس تخصص ثبت‌شده. */
    public function scopeQualifiedFor(Builder $query, int $subjectId): Builder
    {
        return $query
            ->whereHas('subjects', fn (Builder $subjects) => $subjects->where('subjects.id', $subjectId))
            ->where(fn (Builder $teacher) => $teacher
                ->whereDoesntHave('profile')
                ->orWhereHas('profile', fn (Builder $profile) => $profile->where('status', 'active')));
    }

    public function isQualifiedFor(int $subjectId): bool
    {
        return $this->subjects->contains('id', $subjectId);
    }
}
