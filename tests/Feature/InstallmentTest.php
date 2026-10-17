<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| قسط‌بندی شهریه (شماره 54)
*/

class InstallmentTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(int $amount = 3_000_000): Invoice
    {
        return Invoice::issue(Student::factory()->create(), null, $amount);
    }

    private function dates(int $count, string $start = '2026-10-01'): array
    {
        $dates = [];

        foreach (range(0, $count - 1) as $index) {
            $dates[] = now()->parse($start)->addMonths($index)->toDateString();
        }

        return $dates;
    }

    // ── ساخت برنامه ───────────────────────────────────────

    public function test_a_plan_of_four_installments_is_created(): void
    {
        $invoice = $this->invoice(3_000_000);

        Installment::plan($invoice, $this->dates(4));

        $this->assertCount(4, $invoice->installments);
        $this->assertSame(3_000_000, $invoice->plannedAmount());
        $this->assertTrue($invoice->hasInstallmentPlan());
    }

    public function test_the_installments_sum_exactly_to_the_payable(): void
    {
        // ۱۰ میلیون روی ۳ قسط، مساوی نمی‌شود؛ باقی‌مانده باید جایی بنشیند
        $invoice = $this->invoice(10_000_000);

        Installment::plan($invoice, $this->dates(3));

        $this->assertSame(10_000_000, $invoice->plannedAmount());
        $this->assertSame(10_000_000, $invoice->installments()->sum('amount'));
    }

    public function test_the_remainder_goes_to_the_last_installment(): void
    {
        // ۱۰ میلیون روی ۳ قسط: ۳٬۳۳۳٬۳۳۳ و باقی ۱ روی قسط آخر
        $invoice = $this->invoice(10_000_000);

        Installment::plan($invoice, $this->dates(3));

        $rows = $invoice->installments()->orderBy('sequence')->get();

        $this->assertSame(3_333_333, $rows[0]->amount);
        $this->assertSame(3_333_333, $rows[1]->amount);
        $this->assertSame(3_333_334, $rows[2]->amount);
    }

    public function test_the_installments_are_numbered_from_one(): void
    {
        $invoice = $this->invoice();

        Installment::plan($invoice, $this->dates(3));

        $this->assertSame([1, 2, 3], $invoice->installments()->orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_an_uneven_split_needs_at_least_two_installments(): void
    {
        $invoice = $this->invoice();

        $this->expectException(ValidationException::class);

        Installment::plan($invoice, $this->dates(1));
    }

    public function test_a_plan_on_a_paid_invoice_is_refused(): void
    {
        // فاکتور تسویه‌شده قیمتی ندارد که بشکست
        $invoice = $this->invoice();
        Payment::pay($invoice, 3_000_000);

        $this->expectException(ValidationException::class);

        Installment::plan($invoice, $this->dates(4));
    }

    public function test_a_plan_on_an_overpaid_invoice_is_refused(): void
    {
        $invoice = $this->invoice();
        Payment::pay($invoice, 3_500_000);

        $this->expectException(ValidationException::class);

        Installment::plan($invoice, $this->dates(4));
    }

    public function test_the_database_rejects_a_duplicate_sequence(): void
    {
        // شمارهٔ ترتیبی تکراری یعنی دو قسط با یک شماره
        $invoice = $this->invoice();

        Installment::plan($invoice, $this->dates(2));

        $this->expectException(QueryException::class);

        Installment::create([
            'invoice_id' => $invoice->id,
            'sequence' => 1,
            'amount' => 500_000,
            'due_at' => today()->toDateString(),
        ]);
    }

    public function test_a_draft_invoice_can_be_planned(): void
    {
        // پیش‌نویس هنوز قیمت قطعی ندارد ولی قسط‌بندی‌اش بی‌معنی هم نیست
        $invoice = $this->invoice();
        $invoice->update(['status' => InvoiceStatus::Draft]);

        Installment::plan($invoice->fresh(), $this->dates(3));

        $this->assertCount(3, $invoice->fresh()->installments);
    }

    // ── بازبرنامه‌ریزی ─────────────────────────────────────

    public function test_replanning_replaces_the_old_plan(): void
    {
        $invoice = $this->invoice();

        Installment::plan($invoice, $this->dates(4));
        Installment::replan($invoice, $this->dates(2));

        $this->assertCount(2, $invoice->installments()->count() ? $invoice->fresh()->installments : collect());
        $this->assertSame(2, $invoice->installments()->count());
        $this->assertSame(3_000_000, $invoice->plannedAmount());
    }

    public function test_replanning_does_not_touch_money_already_paid(): void
    {
        // خانواده یک قسط داده؛ بازبرنامه‌ریزی نباید پرداخت را پاک کند
        $invoice = $this->invoice();
        Installment::plan($invoice, $this->dates(4));
        Payment::pay($invoice, 750_000);

        Installment::replan($invoice, $this->dates(2));

        $this->assertSame(750_000, $invoice->fresh()->paidAmount());
    }

    // ── معوق ──────────────────────────────────────────────

    public function test_a_future_installment_is_not_overdue(): void
    {
        $invoice = $this->invoice();
        Installment::plan($invoice, [today()->addMonth()->toDateString(), today()->addMonths(2)->toDateString()]);

        $this->assertCount(0, $invoice->overdueInstallments());
        $this->assertSame(0, $invoice->overdueAmount());
    }

    public function test_a_past_installment_is_overdue(): void
    {
        $invoice = $this->invoice();
        Installment::plan($invoice, [
            today()->subMonths(2)->toDateString(),
            today()->addMonth()->toDateString(),
        ]);

        $this->assertCount(1, $invoice->overdueInstallments());
        $this->assertSame(1_500_000, $invoice->overdueAmount());
    }

    public function test_todays_installment_is_due_but_not_overdue(): void
    {
        // سررسید امروز یعنی هنوز فرصت دارند
        $invoice = $this->invoice();
        Installment::plan($invoice, [today()->toDateString(), today()->addMonth()->toDateString()]);

        $this->assertCount(0, $invoice->overdueInstallments());
        $this->assertTrue($invoice->installments()->orderBy('sequence')->first()->isDue());
        $this->assertFalse($invoice->installments()->orderBy('sequence')->first()->isOverdue());
    }

    public function test_a_paid_past_installment_is_not_overdue(): void
    {
        $invoice = $this->invoice();
        Installment::plan($invoice, [
            today()->subMonths(2)->toDateString(),
            today()->subMonth()->toDateString(),
            today()->addMonth()->toDateString(),
        ]);

        Payment::pay($invoice, 1_000_000);

        $this->assertCount(1, $invoice->overdueInstallments());
        $this->assertSame(2, $invoice->overdueInstallments()->first()->sequence);
    }

    public function test_payments_are_applied_to_the_earliest_installment_first(): void
    {
        // خانواده می‌گوید «برای قسط بعدی»؛ اگر بی‌ترتیب تخصیص یابد، قسط اول
        // معوق می‌ماند در حالی که قسط دوم پوشش‌داده‌شده نشان داده می‌شود
        $invoice = $this->invoice();
        Installment::plan($invoice, [
            today()->subMonths(3)->toDateString(),
            today()->subMonths(2)->toDateString(),
            today()->addMonth()->toDateString(),
        ]);

        // یک‌ونیم قسط پرداخت شده: قسط اول پوشش دارد، قسط دوم نیمه‌پرداخت و
        // معوق. اگر پول به ترتیب معکوس تخصیص می‌یافت، قسط اول معوق می‌ماند.
        Payment::pay($invoice, 1_500_000);

        $this->assertSame([2], $invoice->overdueInstallments()->pluck('sequence')->all());
        $this->assertSame(1_000_000, $invoice->overdueAmount());
    }

    public function test_surplus_payment_covers_later_installments(): void
    {
        // خانواده‌ای که کل سال را یک‌جا داده بدهکار معوق نیست
        $invoice = $this->invoice();
        Installment::plan($invoice, [
            today()->subMonth()->toDateString(),
            today()->addMonth()->toDateString(),
        ]);

        Payment::pay($invoice, 3_000_000);

        $this->assertCount(0, $invoice->overdueInstallments());
        $this->assertSame(0, $invoice->overdueAmount());
    }

    public function test_an_invoice_without_a_plan_has_nothing_overdue(): void
    {
        $this->assertCount(0, $this->invoice()->overdueInstallments());
        $this->assertFalse($this->invoice()->hasInstallmentPlan());
    }

    // ── نمایش ──────────────────────────────────────────────

    public function test_the_amount_and_date_are_shown_in_persian(): void
    {
        $invoice = $this->invoice(2_000_000);
        Installment::plan($invoice, ['2026-10-01', '2026-12-01']);

        $installment = $invoice->installments()->orderBy('sequence')->first();

        $this->assertSame(to_persian_digits('1,000,000').' تومان', $installment->amountLabel());
        $this->assertSame('1405/07/09', $installment->dueAtJalali());
    }

    // ── ارتباط‌ها ──────────────────────────────────────────

    public function test_deleting_an_invoice_deletes_its_plan(): void
    {
        $invoice = $this->invoice();
        Installment::plan($invoice, $this->dates(3));

        $invoice->delete();

        $this->assertSame(0, Installment::query()->count());
    }

    public function test_the_plan_belongs_to_its_own_invoice_only(): void
    {
        $a = $this->invoice();
        $b = $this->invoice();

        Installment::plan($a, $this->dates(3));

        $this->assertSame(3, $a->installments()->count());
        $this->assertSame(0, $b->installments()->count());
    }

    public function test_a_payment_on_an_installment_plan_still_closes_the_invoice(): void
    {
        $invoice = $this->invoice();
        Installment::plan($invoice, $this->dates(3));

        Payment::pay($invoice, 1_000_000);
        Payment::pay($invoice, 2_000_000, PaymentMethod::Card, reference: '7788');

        $invoice->refresh();

        $this->assertTrue($invoice->isPaid());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        // قسط‌ها می‌مانند: سابقهٔ توافق با خانواده سند است
        $this->assertSame(3, $invoice->installments()->count());
    }
}
