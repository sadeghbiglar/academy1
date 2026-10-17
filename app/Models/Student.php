<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Status;
use App\Rules\IranianNationalCode;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Student extends Model
{
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
        'guardian_name',
        'guardian_mobile',
        'province',
        'city',
        'address',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',

            // cast به enum روی مقدار ناشناخته خطا می‌دهد، نه اینکه بی‌سروصدا
            // null بدهد. هر ورودی باید از Gender::rule() رد شده باشد
            'gender' => Gender::class,
        ];
    }

    /**
     * پروندهٔ آموزشی. hasOne نه belongsTo: یک دانش‌آموز یک پرونده دارد، ولی
     * ثبت پرونده اختیاری است و نباید جلوی ساخت دانش‌آموز را بگیرد.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** ثبت‌نام‌های دانش‌آموز در دوره‌ها. انتخاب واحد روی همین رابطه کار می‌کند. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** سرپرستی که با او تماس گرفته می‌شود؛ اگر تعیین نشده باشد، اولی. */
    public function primaryGuardian(): HasOne
    {
        return $this->hasOne(Guardian::class)->where('is_primary', true);
    }

    /** وضعیت پرونده، بدون نیاز به null-check در هر view. */
    public function status(): Attribute
    {
        return Attribute::get(fn () => $this->profile?->status);
    }

    public function statusLabel(): string
    {
        return $this->profile?->statusLabel() ?? Status::labelFor(null);
    }

    public function genderLabel(): string
    {
        return $this->gender?->label() ?? '—';
    }

    /**
     * کد ملی هنگام ذخیره بررسی می‌شود، نه فقط در فرم.
     *
     * ایندکس یکتا جلوی دو نفر با یک کد را می‌گیرد ولی جلوی یک کد ملی که اصلاً
     * وجود ندارد را نمی‌گیرد؛ آن خطا باید همین‌جا و همین لحظه گرفته شود، وگرنه
     * داده پرت ثبت می‌شود و ماه‌ها بعد کسی نمی‌فهمد چرا.
     */
    protected static function booted(): void
    {
        static::saving(function (self $student) {
            $code = trim((string) $student->national_code);

            if ($code === '') {
                $student->national_code = null;

                return;
            }

            // normalize یک‌جا انجام می‌شود: ارقام فارسی، خط تیره و نیم‌فاصله
            // حذف می‌شوند و بعد اعتبار سنجیده می‌شود. این شکل ذخیره می‌شود تا
            // جست‌وجو و ایندکس یکتا روی یک شکل باشند.
            $normalized = IranianNationalCode::normalize($code);

            if (! IranianNationalCode::isValid($normalized)) {
                throw ValidationException::withMessages([
                    'national_code' => 'کد ملی معتبر نیست.',
                ]);
            }

            $student->national_code = $normalized;
        });
    }
}
