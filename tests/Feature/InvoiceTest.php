<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| فاکتور شهریه و پرداخت (شماره 52)
*/

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::factory()->create();
    }

    private function invoice(int $amount = 2_000_000): Invoice
    {
        return Invoice::issue($this->student(), Course::factory()->create(), $amount);
    }

    // ── صدور ───────────────────────────────────────────────

    public function test_an_invoice_is_issued(): void
    {
        $student = $this->student();
        $course = Course::factory()->create();

        $invoice = Invoice::issue($student, $course, 2_000_000);

        $this->assertModelExists($invoice);
        $this->assertSame($student->id, $invoice->student_id);
        $this->assertSame($course->id, $invoice->course_id);
        $this->assertSame(2_000_000, $invoice->payable);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_each_invoice_gets_a_unique_number(): void
    {
        // دو فاکتور با یک شماره یعنی «کدام فاکتور؟» موقع شکایت خانواده
        $numbers = collect(range(1, 3))
            ->map(fn () => Invoice::issue($this->student(), null, 100_000)->number)
            ->all();

        $this->assertCount(3, array_unique($numbers));
        $this->assertSame(['IN-000001', 'IN-000002', 'IN-000003'], $numbers);
    }

    public function test_an_invoice_can_exist_without_a_course(): void
    {
        // شهریهٔ ثبت‌نام سالانه به یک دورهٔ خاص گره نیست
        $invoice = Invoice::issue($this->student(), null, 5_000_000);

        $this->assertNull($invoice->course_id);
        $this->assertSame(5_000_000, $invoice->payable);
    }

    public function test_a_negative_amount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Invoice::issue($this->student(), null, -100);
    }

    // ── تخفیف ──────────────────────────────────────────────

    public function test_a_percentage_discount_is_applied(): void
    {
        $invoice = Invoice::issue($this->student(), null, 2_000_000, DiscountType::Percent, 10);

        $this->assertSame(200_000, $invoice->discount_amount);
        $this->assertSame(1_800_000, $invoice->payable);
    }

    public function test_a_fixed_discount_is_applied(): void
    {
        $invoice = Invoice::issue($this->student(), null, 2_000_000, DiscountType::Fixed, 500_000);

        $this->assertSame(500_000, $invoice->discount_amount);
        $this->assertSame(1_500_000, $invoice->payable);
    }

    public function test_a_discount_can_never_exceed_the_amount(): void
    {
        $invoice = Invoice::issue($this->student(), null, 100_000, DiscountType::Percent, 200);

        $this->assertSame(100_000, $invoice->discount_amount);
        $this->assertSame(0, $invoice->payable);
    }

    public function test_no_discount_leaves_the_amount_untouched(): void
    {
        $invoice = Invoice::issue($this->student(), null, 2_000_000);

        $this->assertSame(0, $invoice->discount_amount);
        $this->assertNull($invoice->discount_value);
        $this->assertNull($invoice->discountLabel());
    }

    public function test_a_zero_discount_is_ignored(): void
    {
        // «۰٪ تخفیف» یعنی بدون تخفیف، نه یک رکورد تخفیف با مقدار صفر
        $invoice = Invoice::issue($this->student(), null, 2_000_000, DiscountType::Percent, 0);

        $this->assertSame(0, $invoice->discount_amount);
        $this->assertSame(2_000_000, $invoice->payable);
    }

    public function test_changing_the_discount_does_not_move_money_already_paid(): void
    {
        // payable ذخیره می‌شود؛ عوض کردن تخفیف نباید بدهی را جابه‌جا کند
        $invoice = Invoice::issue($this->student(), null, 2_000_000, DiscountType::Percent, 10);

        Payment::pay($invoice, 1_800_000);

        $invoice->update(['discount_amount' => 500_000]);

        $this->assertSame(0, $invoice->fresh()->remaining());
    }

    // ── وضعیت ──────────────────────────────────────────────

    public function test_a_draft_invoice_is_not_receivable(): void
    {
        // فاکتور پیش‌نویس هنوز بدهی نیست
        $invoice = $this->invoice();
        $invoice->update(['status' => InvoiceStatus::Draft]);

        $this->assertFalse($invoice->fresh()->status->countsAsReceivable());
    }

    public function test_an_issued_invoice_is_receivable(): void
    {
        $this->assertTrue(InvoiceStatus::Issued->countsAsReceivable());
        $this->assertTrue(InvoiceStatus::Partial->countsAsReceivable());
        $this->assertTrue(InvoiceStatus::Overdue->countsAsReceivable());
        $this->assertFalse(InvoiceStatus::Paid->countsAsReceivable());
        $this->assertFalse(InvoiceStatus::Cancelled->countsAsReceivable());
    }

    public function test_a_paid_invoice_cannot_be_deleted(): void
    {
        // پول رفته؛ حذف فاکتور یعنی پاک کردن سند مالی
        $invoice = $this->invoice();
        Payment::pay($invoice, 2_000_000);

        $this->assertFalse($invoice->fresh()->status->deletable());
        $this->assertTrue(InvoiceStatus::Draft->deletable());
    }

    public function test_a_draft_does_not_accept_payment(): void
    {
        $this->assertFalse(InvoiceStatus::Draft->acceptsPayment());
        $this->assertTrue(InvoiceStatus::Issued->acceptsPayment());
        $this->assertTrue(InvoiceStatus::Partial->acceptsPayment());
        $this->assertFalse(InvoiceStatus::Paid->acceptsPayment());
    }

    public function test_only_a_draft_is_editable(): void
    {
        $this->assertTrue(InvoiceStatus::Draft->editable());
        $this->assertFalse(InvoiceStatus::Issued->editable());
    }

    // ── بدهی ──────────────────────────────────────────────

    public function test_an_unpaid_invoice_shows_the_full_amount_as_remaining(): void
    {
        $invoice = $this->invoice(2_000_000);

        $this->assertSame(2_000_000, $invoice->remaining());
        $this->assertFalse($invoice->isPaid());
        $this->assertFalse($invoice->isPartial());
    }

    public function test_a_partial_payment_leaves_the_rest(): void
    {
        $invoice = $this->invoice(2_000_000);

        Payment::pay($invoice, 500_000);

        $invoice->refresh();

        $this->assertSame(500_000, $invoice->paidAmount());
        $this->assertSame(1_500_000, $invoice->remaining());
        $this->assertTrue($invoice->isPartial());
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
    }

    public function test_the_full_payment_closes_the_invoice(): void
    {
        $invoice = $this->invoice(2_000_000);

        Payment::pay($invoice, 1_200_000);
        Payment::pay($invoice, 800_000);

        $invoice->refresh();

        $this->assertTrue($invoice->isPaid());
        $this->assertSame(0, $invoice->remaining());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_an_overpayment_becomes_credit_not_a_negative_balance(): void
    {
        $invoice = $this->invoice(2_000_000);

        Payment::pay($invoice, 2_500_000);

        $invoice->refresh();

        $this->assertSame(0, $invoice->remaining());
        $this->assertSame(500_000, $invoice->credit());
        $this->assertTrue($invoice->isPaid());
    }

    public function test_an_invoice_due_today_is_not_yet_overdue(): void
    {
        // موعدِ امروز یعنی هنوز فرصت دارند؛ صبح روز موعد «معوق» شمرده می‌شد
        $invoice = Invoice::issue($this->student(), null, 2_000_000, dueAt: today()->toDateString());

        $this->assertFalse($invoice->isOverdue());
        $this->assertSame(InvoiceStatus::Issued, $invoice->syncStatus()->status);
    }

    public function test_a_past_due_unpaid_invoice_is_overdue(): void
    {
        $invoice = Invoice::issue(
            $this->student(),
            null,
            2_000_000,
            dueAt: now()->subDay()->toDateString(),
        );

        $invoice->syncStatus()->save();

        $this->assertTrue($invoice->fresh()->isOverdue());
        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_a_paid_invoice_is_not_overdue(): void
    {
        $invoice = Invoice::issue($this->student(), null, 2_000_000, dueAt: now()->subDay()->toDateString());

        Payment::pay($invoice, 2_000_000);

        $this->assertFalse($invoice->fresh()->isOverdue());
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_an_invoice_without_a_due_date_is_not_overdue(): void
    {
        $this->assertFalse($this->invoice()->isOverdue());
    }

    public function test_a_zero_amount_invoice_is_not_paid(): void
    {
        // فاکتور ۰ تومان «تسویه شده» نیست؛ پولی رد و بدل نشده
        $invoice = Invoice::issue($this->student(), null, 0);

        $this->assertFalse($invoice->isPaid());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    // ── نمایش ──────────────────────────────────────────────

    public function test_money_is_shown_in_persian_digits(): void
    {
        $invoice = Invoice::issue($this->student(), null, 2_000_000, DiscountType::Percent, 10);

        $this->assertSame(to_persian_digits('2,000,000').' تومان', $invoice->amountLabel());
        $this->assertSame(to_persian_digits('10').'٪', $invoice->discountLabel());
        $this->assertSame(to_persian_digits('1,800,000').' تومان', $invoice->payableLabel());
    }

    public function test_the_due_date_is_shown_in_jalali(): void
    {
        $invoice = Invoice::issue($this->student(), null, 1_000_000, dueAt: '2026-11-01');

        $this->assertSame('1405/08/10', $invoice->dueAtJalali());
        $this->assertNull($this->invoice()->dueAtJalali());
    }

    public function test_every_invoice_status_has_a_label_and_color(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertStringStartsWith('badge-', $status->color());
        }

        $this->assertSame('—', InvoiceStatus::labelFor(null));
    }

    // ── پرداخت ─────────────────────────────────────────────

    public function test_a_payment_is_recorded_on_the_invoice(): void
    {
        $invoice = $this->invoice(2_000_000);

        $payment = Payment::pay($invoice, 500_000, PaymentMethod::Card, reference: '123456');

        $this->assertModelExists($payment);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame($invoice->student_id, $payment->student_id);
        $this->assertSame(PaymentMethod::Card, $payment->method);
    }

    public function test_a_zero_payment_is_rejected(): void
    {
        $invoice = $this->invoice();

        $this->expectException(ValidationException::class);

        Payment::pay($invoice, 0);
    }

    public function test_a_negative_manual_payment_is_rejected(): void
    {
        $invoice = $this->invoice();

        $this->expectException(ValidationException::class);

        Payment::pay($invoice, -100);
    }

    public function test_a_card_payment_needs_a_reference(): void
    {
        // «کارت به کارت» بدون شمارهٔ پیگیری یعنی پرداخت غیرقابل‌پیگیری
        $invoice = $this->invoice();

        $this->expectException(ValidationException::class);

        Payment::pay($invoice, 100_000, PaymentMethod::Card);
    }

    public function test_cash_needs_no_reference(): void
    {
        $this->assertFalse(PaymentMethod::Cash->requiresReference());
        $this->assertTrue(PaymentMethod::Card->requiresReference());
        $this->assertTrue(PaymentMethod::Cheque->requiresReference());

        $payment = Payment::pay($this->invoice(), 100_000);

        $this->assertNull($payment->reference);
    }

    public function test_the_recorder_of_the_payment_is_kept(): void
    {
        $cashier = User::factory()->create();

        $payment = Payment::pay($this->invoice(), 100_000, by: $cashier);

        $this->assertSame($cashier->id, $payment->user_id);
    }

    public function test_a_payment_survives_the_deletion_of_its_recorder(): void
    {
        // دفتر مالی به سند کارمند وابسته نیست
        $cashier = User::factory()->create();
        $payment = Payment::pay($this->invoice(), 100_000, by: $cashier);

        $cashier->delete();

        $this->assertModelExists($payment->fresh());
        $this->assertNull($payment->fresh()->user_id);
    }

    public function test_reversing_a_payment_subtracts_it(): void
    {
        $invoice = $this->invoice(2_000_000);
        $payment = Payment::pay($invoice, 500_000);

        $payment->reverse();

        $invoice->refresh();

        $this->assertSame(0, $invoice->paidAmount());
        $this->assertSame(2_000_000, $invoice->remaining());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_a_reversal_keeps_the_original_row(): void
    {
        $payment = Payment::pay($this->invoice(), 500_000);

        $reversal = $payment->reverse();

        $this->assertModelExists($payment->fresh());
        $this->assertNotSame($payment->id, $reversal->id);
        $this->assertTrue($reversal->isReversal());
        $this->assertSame(-500_000, $reversal->amount);
    }

    public function test_the_payment_history_is_kept_in_order(): void
    {
        $invoice = $this->invoice(2_000_000);

        Payment::pay($invoice, 1_000_000, paidAt: '2026-10-01');
        Payment::pay($invoice, 1_000_000, paidAt: '2026-10-15');

        $dates = $invoice->payments()->orderBy('paid_at')->pluck('paid_at')
            ->map(fn ($date) => jalali_date($date))->all();

        $this->assertSame(['1405/07/09', '1405/07/23'], $dates);
    }

    public function test_deleting_an_invoice_deletes_its_payments(): void
    {
        $invoice = $this->invoice();
        Payment::pay($invoice, 100_000);

        $invoice->delete();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_every_payment_method_has_a_label(): void
    {
        foreach (PaymentMethod::cases() as $method) {
            $this->assertNotSame('', $method->label());
            $this->assertStringStartsWith('badge-', $method->color());
        }

        $this->assertSame('—', PaymentMethod::labelFor(null));
    }

    // ── حساب دانش‌آموز ─────────────────────────────────────

    public function test_the_student_balance_sums_every_invoice(): void
    {
        $student = $this->student();
        $course = Course::factory()->create();

        Invoice::issue($student, $course, 2_000_000);
        Invoice::issue($student, null, 3_000_000);

        $this->assertSame(5_000_000, $student->totalPayable());
        $this->assertSame(5_000_000, $student->outstanding());
    }

    public function test_the_student_balance_follows_payments(): void
    {
        $student = $this->student();
        $invoice = Invoice::issue($student, null, 2_000_000);

        Payment::pay($invoice, 700_000);

        $this->assertSame(1_300_000, $student->outstanding());
    }

    public function test_a_cancelled_invoice_is_not_payable(): void
    {
        $student = $this->student();
        $invoice = Invoice::issue($student, null, 2_000_000);

        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        $this->assertSame(0, $student->fresh()->totalPayable());
    }

    public function test_a_draft_invoice_is_payable_but_not_a_receivable(): void
    {
        $student = $this->student();
        $invoice = Invoice::issue($student, null, 2_000_000);

        $invoice->update(['status' => InvoiceStatus::Draft]);

        // مبلغ بدهی هست ولی در حساب دریافتی نمی‌آید
        $this->assertSame(2_000_000, $student->fresh()->outstanding());
        $this->assertFalse(InvoiceStatus::Draft->countsAsReceivable());
    }

    public function test_an_overpaid_student_has_credit_not_a_negative_balance(): void
    {
        $student = $this->student();
        $invoice = Invoice::issue($student, null, 1_000_000);

        Payment::pay($invoice, 1_400_000);

        $this->assertSame(0, $student->fresh()->outstanding());
        $this->assertSame(400_000, $student->fresh()->credit());
    }

    public function test_two_students_balances_do_not_mix(): void
    {
        $a = $this->student();
        $b = $this->student();

        Payment::pay(Invoice::issue($a, null, 2_000_000), 500_000);
        Payment::pay(Invoice::issue($b, null, 2_000_000), 1_500_000);

        $this->assertSame(1_500_000, $a->fresh()->outstanding());
        $this->assertSame(500_000, $b->fresh()->outstanding());
    }
}
