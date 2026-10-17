<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سند پذیرش: کارت ملی، شناسنامه، عکس، فرم رضایت.
 *
 * سند به یک کاربر وصل است (چه کسی بارگذاری کرده) ولی به یک دانش‌آموز فقط وقتی
 * سندِ پذیرش باشد.
 */
class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'type',
        'title',
        'file_path',
        'mime_type',
        'size',
        'document_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'size' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    /** انواع سند با برچسب فارسی؛ یک منبع، تا select و جدول یکی باشند. */
    public static function types(): array
    {
        return [
            'national_card' => 'کارت ملی',
            'birth_certificate' => 'شناسنامه',
            'photo' => 'عکس ۳×۴',
            'consent_form' => 'فرم رضایت',
            'previous_report' => 'کارنامه قبلی',
            'other' => 'سایر',
        ];
    }
}
