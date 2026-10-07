<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        ];
    }
    //
}
