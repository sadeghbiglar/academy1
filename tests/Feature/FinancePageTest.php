<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| مالی: فاکتور، دریافت پرداخت، رسید و فاکتور چاپی (شماره 55)
*/

class FinancePageTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(int $amount = 2_000_000): Invoice
    {
        return Invoice::issue(Student::factory()->create(['first_name' => 'سارا', 'last_name' => 'محمدی']), null, $amount);
    }

    private function cashier(): User
    {
        return User::factory()->role(Role::Finance)->create();
    }

    // ── دسترسی ─────────────────────────────────────────────

    public function test_finance_and_reception_can_open_the_finance_screen(): void
    {
        foreach ([Role::Finance, Role::Reception, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->get(route('finance.index'))->assertOk();
        }
    }

    public function test_a_deputy_cannot_open_the_finance_screen(): void
    {
        // معاون آموزشی به پول دسترسی ندارد
        $this->actingAs(User::factory()->role(Role::Deputy)->create());

        $this->get(route('finance.index'))->assertForbidden();
    }

    public function test_a_teacher_cannot_open_the_finance_screen(): void
    {
        $this->actingAs(User::factory()->role(Role::Teacher)->create());

        $this->get(route('finance.index'))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('finance.index'))->assertRedirect(route('login'));
    }

    // ── فهرست ──────────────────────────────────────────────

    public function test_the_list_shows_the_invoices(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice(3_000_000);

        $this->get(route('finance.index'))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('سارا');
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->actingAs($this->cashier());

        $this->get(route('finance.index'))
            ->assertOk()
            ->assertSee('فاکتوری یافت نشد');
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->actingAs($this->cashier());

        $paid = $this->invoice();
        Payment::pay($paid, 2_000_000);

        $unpaid = $this->invoice();

        $this->get(route('finance.index', ['status' => 'paid']))
            ->assertOk()
            ->assertSee($paid->number)
            ->assertDontSee($unpaid->number);
    }

    public function test_the_list_can_be_searched_by_student_name(): void
    {
        // پذیرش اسم را می‌شناسد نه شمارهٔ فاکتور را
        $this->actingAs($this->cashier());

        $target = $this->invoice();
        $other = Invoice::issue(Student::factory()->create(['first_name' => 'نگار']), null, 1_000_000);

        $this->get(route('finance.index', ['search' => 'سارا']))
            ->assertOk()
            ->assertSee($target->number)
            ->assertDontSee($other->number);
    }

    public function test_the_list_can_be_searched_by_national_code(): void
    {
        $this->actingAs($this->cashier());

        $target = Student::factory()->create(['national_code' => '1234567891']);
        $invoice = Invoice::issue($target, null, 1_000_000);
        $other = $this->invoice();

        $this->get(route('finance.index', ['search' => '1234567891']))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertDontSee($other->number);
    }

    // ── دریافت پرداخت ──────────────────────────────────────

    public function test_a_payment_can_be_received_from_the_screen(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice(2_000_000);

        $this->post(route('finance.payments.store', $invoice), [
            'amount' => 500_000,
            'method' => PaymentMethod::Cash->value,
            'paid_at' => today()->toDateString(),
        ])->assertRedirect();

        $invoice->refresh();

        $this->assertSame(500_000, $invoice->paidAmount());
        $this->assertSame(InvoiceStatus::Partial, $invoice->status);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 500_000,
        ]);
    }

    public function test_the_receipting_user_is_recorded(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier);
        $invoice = $this->invoice();

        $this->post(route('finance.payments.store', $invoice), [
            'amount' => 100_000,
            'method' => PaymentMethod::Cash->value,
            'paid_at' => today()->toDateString(),
        ]);

        $this->assertSame($cashier->id, Payment::query()->first()->user_id);
    }

    public function test_a_card_payment_without_a_reference_is_rejected(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice();

        $this->post(route('finance.payments.store', $invoice), [
            'amount' => 100_000,
            'method' => PaymentMethod::Card->value,
            'paid_at' => today()->toDateString(),
        ]);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_zero_payment_is_rejected(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice();

        $this->post(route('finance.payments.store', $invoice), [
            'amount' => 0,
            'method' => PaymentMethod::Cash->value,
            'paid_at' => today()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_payment_on_a_paid_invoice_is_refused(): void
    {
        // پولی که رفته را دوباره نمی‌گیریم؛ این خطا باید پیام بدهد نه ۵۰۰
        $this->actingAs($this->cashier());
        $invoice = $this->invoice();
        Payment::pay($invoice, 2_000_000);

        $this->from(route('finance.show', $invoice))
            ->post(route('finance.payments.store', $invoice), [
                'amount' => 100_000,
                'method' => PaymentMethod::Cash->value,
                'paid_at' => today()->toDateString(),
            ])
            ->assertRedirect(route('finance.show', $invoice))
            ->assertSessionHas('error');

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_payment_on_a_cancelled_invoice_is_refused(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice();
        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        $this->from(route('finance.show', $invoice))
            ->post(route('finance.payments.store', $invoice), [
                'amount' => 100_000,
                'method' => PaymentMethod::Cash->value,
                'paid_at' => today()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_deputy_cannot_receive_money_even_with_a_valid_form(): void
    {
        // مجوز در مسیر است، نه در فرم
        $this->actingAs(User::factory()->role(Role::Deputy)->create());
        $invoice = $this->invoice();

        $this->post(route('finance.payments.store', $invoice), [
            'amount' => 100_000,
            'method' => PaymentMethod::Cash->value,
            'paid_at' => today()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    // ── جزئیات ─────────────────────────────────────────────

    public function test_the_detail_page_shows_the_installment_plan(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice(3_000_000);

        Installment::plan($invoice, [
            today()->subMonth()->toDateString(),
            today()->addMonth()->toDateString(),
        ]);

        $this->get(route('finance.show', $invoice))
            ->assertOk()
            ->assertSee('برنامهٔ اقساط')
            ->assertSee('معوق');
    }

    public function test_the_detail_page_hides_the_payment_form_for_a_paid_invoice(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice();
        Payment::pay($invoice, 2_000_000);

        $this->get(route('finance.show', $invoice->fresh()))
            ->assertOk()
            ->assertSee('پرداخت نمی‌پذیرد');
    }

    public function test_the_detail_page_shows_an_empty_payment_history(): void
    {
        $this->actingAs($this->cashier());

        $this->get(route('finance.show', $this->invoice()))
            ->assertOk()
            ->assertSee('پرداختی ثبت نشده');
    }

    public function test_a_teacher_cannot_open_someone_elses_invoice(): void
    {
        $this->actingAs(User::factory()->role(Role::Teacher)->create());

        $this->get(route('finance.show', $this->invoice()))->assertForbidden();
    }

    // ── چاپ ────────────────────────────────────────────────

    public function test_the_printable_invoice_has_no_panel_chrome(): void
    {
        // پنل مدیریت منو و سایه دارد و روی کاغذ می‌افتد؛ سند مالی باید تمیز باشد
        $this->actingAs($this->cashier());

        $response = $this->get(route('finance.invoice.print', $this->invoice()))->assertOk();

        $response->assertDontSee('drawer-side');
        $response->assertDontSee('خروج از حساب');
    }

    public function test_the_printable_invoice_shows_the_money_in_persian_digits(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice(2_000_000);

        $this->get(route('finance.invoice.print', $invoice))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee(to_persian_digits('2,000,000'))
            ->assertSee('تومان');
    }

    public function test_the_printable_invoice_shows_the_dates_in_jalali(): void
    {
        $this->actingAs($this->cashier());
        $invoice = Invoice::issue(
            Student::factory()->create(),
            null,
            1_000_000,
            dueAt: '2026-11-01',
        );

        $this->get(route('finance.invoice.print', $invoice))
            ->assertOk()
            ->assertSee('1405/08/10');
    }

    public function test_the_printable_invoice_uses_the_configured_academy_name(): void
    {
        $this->actingAs($this->cashier());
        Setting::put(Setting::ACADEMY_NAME, 'آموزشگاه دانش‌نویس');

        $this->get(route('finance.invoice.print', $this->invoice()))
            ->assertOk()
            ->assertSee('آموزشگاه دانش‌نویس');
    }

    public function test_the_printable_invoice_falls_back_when_no_name_is_set(): void
    {
        // نبودن تنظیم نباید صفحهٔ چاپ را خراب کند
        $this->actingAs($this->cashier());

        $this->assertSame('آموزشگاه', Setting::academyName());

        $this->get(route('finance.invoice.print', $this->invoice()))->assertOk();
    }

    public function test_the_receipt_is_printable_and_narrow(): void
    {
        // رسید روی کارت کوچک چاپ می‌شود، نه روی A4
        $this->actingAs($this->cashier());
        $invoice = $this->invoice();
        $payment = Payment::pay($invoice, 500_000);

        $this->get(route('finance.receipt.print', $payment))
            ->assertOk()
            ->assertSee('رسید پرداخت')
            ->assertSee(to_persian_digits('500,000'))
            ->assertSee('80mm');
    }

    public function test_the_receipt_shows_the_remaining_balance(): void
    {
        $this->actingAs($this->cashier());
        $invoice = $this->invoice(2_000_000);
        $payment = Payment::pay($invoice, 500_000);

        $this->get(route('finance.receipt.print', $payment))
            ->assertOk()
            ->assertSee(to_persian_digits('1,500,000'));
    }

    public function test_a_deputy_cannot_print_an_invoice(): void
    {
        $this->actingAs(User::factory()->role(Role::Deputy)->create());

        $this->get(route('finance.invoice.print', $this->invoice()))->assertForbidden();
    }

    public function test_a_guest_cannot_print_a_receipt(): void
    {
        $payment = Payment::pay($this->invoice(), 100_000);

        $this->get(route('finance.receipt.print', $payment))->assertRedirect(route('login'));
    }

    public function test_the_print_view_shows_the_course_name_when_there_is_one(): void
    {
        $this->actingAs($this->cashier());
        $invoice = Invoice::issue(Student::factory()->create(), Course::factory()->create(['title' => 'ریاضی یازدهم']), 1_000_000);

        $this->get(route('finance.invoice.print', $invoice))
            ->assertOk()
            ->assertSee('ریاضی یازدهم');
    }
}
