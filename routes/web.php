<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسیرهای سامانه
|--------------------------------------------------------------------------
| هر مسیر حساس با `role:` محافظت می‌شود. پنهان کردن لینک از منو امنیت نیست؛
| مجوز باید در لایه route اعمال شود، وگرنه هر کسی که آدرس را بداند دورش
| می‌زند.
*/

Route::redirect('/', '/dashboard');

// ── عمومی ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')->name('logout');

// ── نیازمند ورود ─────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

    // دانش‌آموزان: مدیر، معاون، پذیرش، و امور مالی که فقط وضعیت بدهی را می‌بیند
    Route::livewire('/students', 'pages::students.index')
        ->middleware('role:admin,deputy,finance,reception')
        ->name('students.index');

    // اساتید و حقوق: فقط مدیر و معاون. امور مالی دسترسی ندارد چون حقوق
    // همان‌جاست و دبیر نباید لیست حقوق را ببیند.
    Route::livewire('/teachers', 'pages::teachers.index')
        ->middleware('role:admin,deputy')
        ->name('teachers.index');

    Route::livewire('/profile', 'pages::profile.index')->name('profile');

    // مدیریت کاربران: فقط مدیر
    Route::livewire('/users', 'pages::users.index')
        ->middleware('role:admin')
        ->name('users.index');
});
