<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',

            // cast به enum یعنی مقدار ناشناخته به null تبدیل می‌شود، نه اینکه
            // کل رکورد بترکد؛ برچسب نامعتبر در UI خط تیره می‌شود و قابل دیدن
            // است — برخلاف داده‌ای که بی‌سروصدا گم می‌شود
            'gender' => Gender::class,
            'status' => Status::class,
        ];
    }

    public function genderLabel(): string
    {
        return $this->gender?->label() ?? '—';
    }

    /** پیش‌فرض دانش‌آموز تازه‌ثبت‌شده «در حال ثبت‌نام» است، نه «فعال». */
    protected $attributes = [
        'status' => 'enrolling',
    ];
    //
}
