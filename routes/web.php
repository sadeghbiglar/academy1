<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\ReportController;
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

    // پروندهٔ یک دانش‌آموز. نقش‌ها مثل فهرست‌اند و خود صفحه بخش‌هایی را که
    // آن نقش اجازه ندارد (نمره برای امور مالی) پنهان می‌کند.
    Route::livewire('/students/{student}', 'pages::students.show')
        ->middleware('role:admin,deputy,finance,reception')
        ->name('students.show');

    // دوره‌ها: مدیر، معاون (ثبت‌نام و ارزیابی)، پذیرش (ثبت‌نام و فاکتور) و
    // دبیر برای «کلاس‌های خودش». امور مالی دوره را می‌بیند ولی ویرایشش نه.
    Route::livewire('/courses', 'pages::courses.index')
        ->middleware('role:admin,deputy,reception,teacher')
        ->name('courses.index');

    // اساتید و حقوق: فقط مدیر و معاون. امور مالی دسترسی ندارد چون حقوق
    // همان‌جاست و دبیر نباید لیست حقوق را ببیند.
    Route::livewire('/teachers', 'pages::teachers.index')
        ->middleware('role:admin,deputy')
        ->name('teachers.index');

    // مالی و فاکتور. مدیر، امور مالی و پذیرش — پذیرش فاکتور اولیه را می‌سازد.
    // دبیر و معاون به پول دسترسی ندارند.
    Route::get('/finance/invoices', [FinanceController::class, 'index'])
        ->middleware('role:admin,finance,reception')
        ->name('finance.index');

    Route::get('/finance/invoices/{invoice}', [FinanceController::class, 'show'])
        ->middleware('role:admin,finance,reception')
        ->name('finance.show');

    Route::post('/finance/invoices/{invoice}/payments', [FinanceController::class, 'store'])
        ->middleware('role:admin,finance,reception')
        ->name('finance.payments.store');

    // چاپ: view جدا بدون layout پنل، چون منو و سایهٔ پنل روی کاغذ می‌افتند
    Route::get('/finance/invoices/{invoice}/print', [FinanceController::class, 'printInvoice'])
        ->middleware('role:admin,finance,reception')
        ->name('finance.invoice.print');

    Route::get('/finance/payments/{payment}/receipt', [FinanceController::class, 'printReceipt'])
        ->middleware('role:admin,finance,reception')
        ->name('finance.receipt.print');

    // گزارش وصولی و بدهکاران: همان نقش‌های مالی
    Route::get('/reports/balances', [ReportController::class, 'balances'])
        ->middleware('role:admin,finance,reception')
        ->name('reports.balances');

    // گزارش‌های آموزشی: مدیر، معاون و دبیر. امور مالی نمره نمی‌بیند.
    Route::get('/reports/academic', [ReportController::class, 'academic'])
        ->middleware('role:admin,deputy,teacher')
        ->name('reports.academic');

    // صورت سود و زیان: فقط مدیر و امور مالی. معاون آموزشی به هزینه‌ها دسترسی ندارد.
    Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])
        ->middleware('role:admin,finance')
        ->name('reports.profit-and-loss');

    Route::livewire('/profile', 'pages::profile.index')->name('profile');

    // مدیریت کاربران: فقط مدیر
    Route::livewire('/users', 'pages::users.index')
        ->middleware('role:admin')
        ->name('users.index');
});
