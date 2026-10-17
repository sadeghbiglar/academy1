<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\BalanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| گزارش وصولی و بدهکاران (شماره 56)
*/

class BalanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function report(): BalanceReport
    {
        return new BalanceReport;
    }

    private function invoice(Student $student, int $amount, string $issuedAt = '2026-10-01'): Invoice
    {
        // تاریخ صدور صریح ست می‌شود: Invoice::issue() همیشه امروز را می‌گذارد
        // و بدون این، فیلتر بازه هیچ‌وقت چیزی را کنار نمی‌گذارد
        $invoice = Invoice::issue($student, null, $amount);

        $invoice->update(['issued_at' => $issuedAt]);

        return $invoice->fresh();
    }

    // ── مانده ──────────────────────────────────────────────

    public function test_an_unpaid_invoice_is_the_outstanding_amount(): void
    {
        $student = Student::factory()->create();

        $this->invoice($student, 2_000_000);

        $row = $this->report()->rowFor($student);

        $this->assertSame(2_000_000, $row['total']);
        $this->assertSame(0, $row['paid']);
        $this->assertSame(2_000_000, $row['outstanding']);
    }

    public function test_a_paid_invoice_leaves_nothing_outstanding(): void
    {
        $student = Student::factory()->create();
        Payment::pay($this->invoice($student, 2_000_000), 2_000_000);

        $row = $this->report()->rowFor($student);

        $this->assertSame(0, $row['outstanding']);
        $this->assertSame('تسویه', $row['status']);
    }

    public function test_a_partial_payment_splits_the_balance(): void
    {
        $student = Student::factory()->create();
        Payment::pay($this->invoice($student, 2_000_000), 700_000);

        $row = $this->report()->rowFor($student);

        $this->assertSame(700_000, $row['paid']);
        $this->assertSame(1_300_000, $row['outstanding']);
        $this->assertSame('نیمه‌پرداخت', $row['status']);
    }

    public function test_a_student_without_invoices_is_all_zeroes(): void
    {
        $row = $this->report()->rowFor(Student::factory()->create());

        $this->assertSame(0, $row['total']);
        $this->assertSame(0, $row['paid']);
        $this->assertSame(0, $row['outstanding']);
        $this->assertNull($row['last_paid_at']);
    }

    public function test_a_student_with_no_account_is_not_called_settled(): void
    {
        // «تسویه» یعنی پول جابه‌جا شده و بدهی نمانده. بدون فاکتور هیچ ادعایی
        // وجود ندارد؛ گفتن تسویه برای کسی که هرگز صورت‌حساب نداشته غلط است
        $row = $this->report()->rowFor(Student::factory()->create());

        $this->assertSame('بدون حساب', $row['status']);
    }

    public function test_a_cancelled_invoice_is_not_counted(): void
    {
        $student = Student::factory()->create();
        $invoice = $this->invoice($student, 3_000_000);
        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        $this->assertSame(0, $this->report()->rowFor($student)['total']);
    }

    public function test_a_draft_invoice_counts_as_a_debt_but_not_as_a_receivable(): void
    {
        // پول واقعاً طلب آموزشگاه است؛ فقط در گزارش وصولی نمی‌آید
        $student = Student::factory()->create();
        $invoice = $this->invoice($student, 1_000_000);
        $invoice->update(['status' => InvoiceStatus::Draft]);

        $report = $this->report();

        $this->assertSame(1_000_000, $report->rowFor($student)['outstanding']);
        $this->assertSame(0, $report->totalOutstanding());
    }

    public function test_a_reversal_reduces_the_paid_amount(): void
    {
        $student = Student::factory()->create();
        $payment = Payment::pay($this->invoice($student, 2_000_000), 500_000);
        $payment->reverse();

        $row = $this->report()->rowFor($student);

        $this->assertSame(0, $row['paid']);
        $this->assertSame(2_000_000, $row['outstanding']);
    }

    public function test_the_last_payment_date_is_reported(): void
    {
        $student = Student::factory()->create();
        Payment::pay($this->invoice($student, 2_000_000), 100_000, paidAt: '2026-10-05');
        Payment::pay($this->invoice($student, 2_000_000), 100_000, paidAt: '2026-11-20');

        $row = $this->report()->rowFor($student);

        $this->assertNotNull($row['last_paid_at']);
        $this->assertSame('2026-11-20', $row['last_paid_at']->toDateString());
    }

    // ── بازهٔ تاریخ ────────────────────────────────────────

    public function test_the_range_filters_invoices_by_issue_date(): void
    {
        $student = Student::factory()->create();
        $this->invoice($student, 1_000_000, '2026-09-01');
        $this->invoice($student, 2_000_000, '2026-11-01');

        $row = $this->report()->rowFor($student, '2026-10-01', '2026-10-31');

        $this->assertSame(0, $row['total']);
    }

    public function test_the_range_keeps_invoices_inside_it(): void
    {
        $student = Student::factory()->create();
        $this->invoice($student, 1_000_000, '2026-09-01');
        $this->invoice($student, 2_000_000, '2026-11-01');

        $row = $this->report()->rowFor($student, '2026-11-01', '2026-11-30');

        $this->assertSame(2_000_000, $row['total']);
    }

    public function test_a_payment_outside_the_range_does_not_clear_the_debt(): void
    {
        // پرداختِ خارج از بازه نباید بدهیِ داخل بازه را پاک کند
        $student = Student::factory()->create();
        $this->invoice($student, 2_000_000, '2026-10-01');

        Payment::pay(
            $this->invoice($student, 2_000_000, '2026-11-01'),
            2_000_000,
            paidAt: '2026-11-05',
        );

        $row = $this->report()->rowFor($student, '2026-10-01', '2026-10-31');

        $this->assertSame(2_000_000, $row['total']);
        $this->assertSame(0, $row['paid']);
        $this->assertSame(2_000_000, $row['outstanding']);
    }

    public function test_a_payment_inside_the_range_counts(): void
    {
        $student = Student::factory()->create();
        $invoice = $this->invoice($student, 2_000_000, '2026-10-01');

        Payment::pay($invoice, 500_000, paidAt: '2026-10-20');

        $row = $this->report()->rowFor($student, '2026-10-01', '2026-10-31');

        $this->assertSame(500_000, $row['paid']);
        $this->assertSame(1_500_000, $row['outstanding']);
    }

    public function test_no_range_means_everything(): void
    {
        $student = Student::factory()->create();
        $this->invoice($student, 1_000_000, '2026-09-01');
        $this->invoice($student, 2_000_000, '2026-11-01');

        $this->assertSame(3_000_000, $this->report()->rowFor($student)['total']);
    }

    // ── جمع‌ها ─────────────────────────────────────────────

    public function test_the_totals_are_the_sum_of_the_rows(): void
    {
        $a = Student::factory()->create();
        $b = Student::factory()->create();

        $this->invoice($a, 2_000_000);
        $this->invoice($b, 3_000_000);
        Payment::pay(Invoice::query()->where('student_id', $a->id)->first(), 500_000);

        $rows = $this->report()->build(collect([$a, $b]));
        $totals = $this->report()->totals($rows);

        $this->assertSame(5_000_000, $totals['total']);
        $this->assertSame(500_000, $totals['paid']);
        $this->assertSame(4_500_000, $totals['outstanding']);
    }

    public function test_the_biggest_debtor_comes_first(): void
    {
        $small = Student::factory()->create();
        $big = Student::factory()->create();

        $this->invoice($small, 500_000);
        $this->invoice($big, 5_000_000);

        $rows = $this->report()->build(collect([$small, $big]));

        $this->assertSame($big->id, $rows->first()['student']->id);
        $this->assertSame($small->id, $rows->last()['student']->id);
    }

    public function test_collections_are_grouped_by_method(): void
    {
        $a = Student::factory()->create();
        $b = Student::factory()->create();

        Payment::pay($this->invoice($a, 1_000_000), 100_000, PaymentMethod::Cash);
        Payment::pay($this->invoice($b, 1_000_000), 200_000, PaymentMethod::Cash);
        Payment::pay($this->invoice($b, 1_000_000), 300_000, PaymentMethod::Card, reference: '9');

        $byMethod = $this->report()->collectedByMethod();

        $this->assertSame(300_000, $byMethod[PaymentMethod::Cash->value]);
        $this->assertSame(300_000, $byMethod[PaymentMethod::Card->value]);
    }

    public function test_collections_respect_the_range(): void
    {
        $student = Student::factory()->create();
        $invoice = $this->invoice($student, 2_000_000);

        Payment::pay($invoice, 100_000, paidAt: '2026-09-01');
        Payment::pay($invoice, 200_000, paidAt: '2026-11-01');

        $byMethod = $this->report()->collectedByMethod('2026-11-01', '2026-11-30');

        $this->assertSame(200_000, $byMethod[PaymentMethod::Cash->value]);
    }

    public function test_the_academy_outstanding_excludes_paid_and_draft(): void
    {
        $student = Student::factory()->create();

        Payment::pay($this->invoice($student, 1_000_000), 1_000_000);
        $this->invoice($student, 2_000_000);

        $draft = $this->invoice($student, 5_000_000);
        $draft->update(['status' => InvoiceStatus::Draft]);

        $this->assertSame(2_000_000, $this->report()->totalOutstanding());
    }

    // ── صفحه ───────────────────────────────────────────────

    public function test_the_report_page_is_open_to_finance(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $this->get(route('reports.balances'))->assertOk();
    }

    public function test_a_deputy_cannot_open_the_balance_report(): void
    {
        $this->actingAs(User::factory()->role(Role::Deputy)->create());

        $this->get(route('reports.balances'))->assertForbidden();
    }

    public function test_the_page_lists_every_student_even_without_invoices(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create(['first_name' => 'نیلوفر']);

        $this->get(route('reports.balances'))
            ->assertOk()
            ->assertSee('نیلوفر')
            ->assertSee('بدون حساب');
    }

    public function test_the_page_shows_the_totals(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();

        Payment::pay($this->invoice($student, 3_000_000), 1_000_000);

        $this->get(route('reports.balances'))
            ->assertOk()
            ->assertSee(to_persian_digits('3,000,000'))
            ->assertSee(to_persian_digits('1,000,000'))
            ->assertSee(to_persian_digits('2,000,000'));
    }

    public function test_the_page_can_show_only_debtors(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $payer = Student::factory()->create(['first_name' => 'پرداخت‌کرده']);
        $debtor = Student::factory()->create(['first_name' => 'بدهکار']);

        Payment::pay($this->invoice($payer, 1_000_000), 1_000_000);
        $this->invoice($debtor, 1_000_000);

        $this->get(route('reports.balances', ['only_debtors' => 1]))
            ->assertOk()
            ->assertSee('بدهکار')
            ->assertDontSee('پرداخت‌کرده');
    }

    public function test_a_jalali_range_is_accepted_and_applied(): void
    {
        // کاربر تاریخ شمسی می‌نویسد؛ بازه باید درست اعمال شود
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();

        $this->invoice($student, 5_000_000);

        // ۲۰۲۶/۱۰/۰۱ میلادی ≈ ۱۴۰۵/۰۷/۱۰ شمسی
        $this->get(route('reports.balances', ['from' => '1405/07/01', 'to' => '1405/07/20']))
            ->assertOk()
            ->assertSee(to_persian_digits('5,000,000'));

        $this->get(route('reports.balances', ['from' => '1404/01/01', 'to' => '1404/01/20']))
            ->assertOk()
            ->assertDontSee(to_persian_digits('5,000,000'));
    }

    public function test_a_persian_digit_range_is_accepted(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();
        $this->invoice($student, 4_000_000);

        $this->get(route('reports.balances', ['from' => '۱۴۰۵/۰۷/۰۱', 'to' => '۱۴۰۵/۰۷/۳۰']))
            ->assertOk()
            ->assertSee(to_persian_digits('4,000,000'));
    }

    public function test_an_invalid_jalali_date_falls_back_to_no_range(): void
    {
        // تاریخ نامعتبر باید بازهٔ خالی بدهد، نه خطای ۵۰۰ و نه پذیرش بی‌صدا
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();
        $this->invoice($student, 1_500_000);

        $this->get(route('reports.balances', ['from' => 'not-a-date', 'to' => 'also-bad']))
            ->assertOk()
            ->assertSee(to_persian_digits('1,500,000'));
    }

    public function test_the_page_shows_collections_by_method(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();

        Payment::pay($this->invoice($student, 2_000_000), 500_000, PaymentMethod::Card, reference: '77');

        $this->get(route('reports.balances'))
            ->assertOk()
            ->assertSee('کارت به کارت')
            ->assertSee(to_persian_digits('500,000'));
    }

    public function test_the_page_shows_the_last_payment_in_jalali(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();

        Payment::pay($this->invoice($student, 2_000_000), 100_000, paidAt: '2026-11-01');

        $this->get(route('reports.balances'))
            ->assertOk()
            ->assertSee('1405/08/10');
    }
}
