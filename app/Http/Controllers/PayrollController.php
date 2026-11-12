<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Illuminate\Contracts\View\View;

/**
 * فیش حقوقی چاپی.
 *
 * جدا از پنل است مثل چاپِ فاکتور: منو، سایه و دکمه‌ها روی کاغذ می‌افتند و
 * فیشی که نیمه‌اش منو است، سند پرداخت نیست.
 */
class PayrollController extends Controller
{
    public function print(Payroll $payroll): View
    {
        return view('payrolls.print.payslip', [
            'payroll' => $payroll->load('teacher'),
        ]);
    }
}
