<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\ProfitAndLoss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| هزینه‌ها و صورت سود و زیان (شماره 58)
*/

class ProfitAndLossTest extends TestCase
{
    use RefreshDatabase;

    private function pnl(): ProfitAndLoss
    {
        return new ProfitAndLoss;
    }

    private function income(int $amount, string $paidAt = '2026-10-10'): Payment
    {
        $invoice = Invoice::issue(Student::factory()->create(), null, $amount);

        return Payment::pay($invoice, $amount, PaymentMethod::Cash, $paidAt);
    }

    private function expense(ExpenseCategory $category, int $amount, string $spentAt = '2026-10-11'): Expense
    {
        return Expense::record($category, 'هزینهٔ آزمایشی', $amount, $spentAt);
    }

    // ── هزینه ──────────────────────────────────────────────

    public function test_an_expense_is_recorded(): void
    {
        $expense = Expense::record(ExpenseCategory::Rent, 'اجارهٔ محل', 25_000_000, '2026-10-01');

        $this->assertModelExists($expense);
        $this->assertSame(ExpenseCategory::Rent, $expense->category);
        $this->assertSame('اجاره', $expense->categoryLabel());
    }

    public function test_a_zero_expense_is_rejected(): void
    {
        // هزینهٔ صفر در گزارش سود یعنی سودِ ساختگی
        $this->expectException(ValidationException::class);

        Expense::record(ExpenseCategory::Other, 'چیزی', 0, '2026-10-01');
    }

    public function test_a_negative_expense_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Expense::record(ExpenseCategory::Other, 'چیزی', -5_000, '2026-10-01');
    }

    public function test_an_expense_survives_the_deletion_of_its_recorder(): void
    {
        $user = User::factory()->create();
        $expense = Expense::record(ExpenseCategory::Rent, 'اجاره', 1_000_000, '2026-10-01', $user);

        $user->delete();

        $this->assertModelExists($expense->fresh());
        $this->assertNull($expense->fresh()->user_id);
    }

    public function test_the_spent_date_is_shown_in_jalali(): void
    {
        $this->assertSame('1405/07/09', $this->expense(ExpenseCategory::Rent, 100, '2026-10-01')->spentAtJalali());
    }

    public function test_every_category_has_a_label_and_a_colour(): void
    {
        foreach (ExpenseCategory::cases() as $category) {
            $this->assertNotSame('', $category->label());
            $this->assertStringStartsWith('badge-', $category->color());
        }

        $this->assertSame('—', ExpenseCategory::labelFor(null));
    }

    // ── سود و زیان ─────────────────────────────────────────

    public function test_profit_is_income_minus_expenses(): void
    {
        $this->income(10_000_000);
        $this->expense(ExpenseCategory::Rent, 4_000_000);

        $this->assertSame(10_000_000, $this->pnl()->income());
        $this->assertSame(4_000_000, $this->pnl()->expenses());
        $this->assertSame(6_000_000, $this->pnl()->profit());
        $this->assertTrue($this->pnl()->isProfit());
    }

    public function test_a_loss_is_reported_as_a_negative_profit(): void
    {
        $this->income(3_000_000);
        $this->expense(ExpenseCategory::Salary, 5_000_000);

        $this->assertSame(-2_000_000, $this->pnl()->profit());
        $this->assertFalse($this->pnl()->isProfit());
    }

    public function test_an_empty_period_is_a_zero_not_a_loss(): void
    {
        $this->assertSame(0, $this->pnl()->profit());
        $this->assertTrue($this->pnl()->isProfit());
    }

    public function test_income_counts_collected_money_not_invoices_issued(): void
    {
        // فروشِ نسخه‌ای که هنوز پرداخت نشده سود نیست
        Invoice::issue(Student::factory()->create(), null, 5_000_000);

        $this->assertSame(0, $this->pnl()->income());
    }

    public function test_a_reversal_reduces_the_income(): void
    {
        $payment = $this->income(2_000_000);
        $payment->reverse();

        $this->assertSame(0, $this->pnl()->income());
    }

    // ── مرزهای بازه ────────────────────────────────────────

    public function test_a_jalali_year_range_covers_the_whole_year(): void
    {
        // اول فروردین تا آخر اسفند
        $this->income(5_000_000, '2026-03-21');   // ۱ فروردین ۱۴۰۵
        $this->income(7_000_000, '2027-03-20');   // آخر اسفند ۱۴۰۵
        $this->expense(ExpenseCategory::Rent, 1_000_000, '2026-06-01');

        // بازهٔ کل سال شمسی ۱۴۰۵: اول فروردین تا آخر اسفند
        $from = '2026-03-21';
        $to = '2027-03-20';

        $this->assertSame(12_000_000, $this->pnl()->income($from, $to));
        $this->assertSame(1_000_000, $this->pnl()->expenses($from, $to));
        $this->assertSame(11_000_000, $this->pnl()->profit($from, $to));
    }

    public function test_the_range_excludes_money_outside_it(): void
    {
        $this->income(5_000_000, '2026-10-10');
        $this->income(9_000_000, '2026-12-10');

        $this->assertSame(5_000_000, $this->pnl()->income('2026-10-01', '2026-10-31'));
    }

    public function test_the_range_boundaries_are_inclusive(): void
    {
        $this->income(1_000_000, '2026-10-01');
        $this->income(2_000_000, '2026-10-31');

        $this->assertSame(3_000_000, $this->pnl()->income('2026-10-01', '2026-10-31'));
    }

    public function test_an_expense_is_counted_in_the_month_it_happened_not_when_typed(): void
    {
        // قبض برق را ده روز بعد وارد می‌کنند
        $this->expense(ExpenseCategory::Utilities, 2_000_000, '2026-09-25');
        $this->expense(ExpenseCategory::Utilities, 3_000_000, '2026-10-02');

        $this->assertSame(3_000_000, $this->pnl()->expenses('2026-10-01', '2026-10-31'));
        $this->assertSame(2_000_000, $this->pnl()->expenses('2026-09-01', '2026-09-30'));
    }

    // ── دسته‌بندی ─────────────────────────────────────────

    public function test_expenses_are_grouped_by_category_largest_first(): void
    {
        $this->expense(ExpenseCategory::Rent, 5_000_000);
        $this->expense(ExpenseCategory::Utilities, 1_000_000);
        $this->expense(ExpenseCategory::Rent, 3_000_000);

        $rows = $this->pnl()->byCategory();

        $this->assertSame('اجاره', $rows->first()['label']);
        $this->assertSame(8_000_000, $rows->first()['total']);
        $this->assertSame(1_000_000, $rows->last()['total']);
    }

    public function test_rent_and_salary_are_fixed_costs(): void
    {
        $this->expense(ExpenseCategory::Rent, 5_000_000);
        $this->expense(ExpenseCategory::Utilities, 1_000_000);

        $split = $this->pnl()->fixedVsVariable();

        $this->assertSame(5_000_000, $split['fixed']);
        $this->assertSame(1_000_000, $split['variable']);
    }

    public function test_the_category_totals_match_the_total_expense(): void
    {
        $this->expense(ExpenseCategory::Rent, 5_000_000);
        $this->expense(ExpenseCategory::Salary, 2_000_000);
        $this->expense(ExpenseCategory::Marketing, 1_000_000);

        $this->assertSame(
            $this->pnl()->expenses(),
            (int) $this->pnl()->byCategory()->sum('total'),
        );
    }

    // ── روند ماهانه ────────────────────────────────────────

    public function test_monthly_buckets_are_in_jalali(): void
    {
        $this->income(1_000_000, '2026-10-05');
        $this->income(2_000_000, '2026-11-05');

        $months = $this->pnl()->monthly('2026-10-01', '2026-11-30');

        $this->assertSame(['1405/07', '1405/08'], $months->pluck('month')->all());
        $this->assertSame(1_000_000, $months->first()['income']);
        $this->assertSame(2_000_000, $months->last()['income']);
    }

    public function test_a_quiet_month_is_still_shown_as_zero(): void
    {
        // حذف ماه خالی فاصلهٔ زمانی را پنهان می‌کند و «رشد» را بزرگ‌تر نشان می‌دهد
        $this->income(1_000_000, '2026-10-05');
        $this->income(3_000_000, '2026-12-05');

        $months = $this->pnl()->monthly('2026-10-01', '2026-12-31');

        // مهر: یک میلیون، آبان: سکوت کامل، آذر: سه میلیون
        $this->assertCount(3, $months);
        $this->assertSame(1_000_000, $months->first()['income']);
        $this->assertSame(0, $months->get(1)['income']);
        $this->assertSame(3_000_000, $months->last()['income']);
    }

    public function test_the_monthly_profit_is_income_minus_expenses(): void
    {
        $this->income(5_000_000, '2026-10-05');
        $this->expense(ExpenseCategory::Rent, 2_000_000, '2026-10-20');

        $month = $this->pnl()->monthly('2026-10-01', '2026-10-31')->first();

        $this->assertSame(5_000_000, $month['income']);
        $this->assertSame(2_000_000, $month['expenses']);
        $this->assertSame(3_000_000, $month['profit']);
    }

    public function test_the_monthly_income_matches_the_period_income(): void
    {
        $this->income(1_000_000, '2026-10-05');
        $this->income(2_000_000, '2026-10-20');
        $this->income(4_000_000, '2026-11-05');

        $months = $this->pnl()->monthly('2026-10-01', '2026-11-30');

        $this->assertSame($this->pnl()->income('2026-10-01', '2026-11-30'), (int) $months->sum('income'));
    }

    public function test_no_data_at_all_still_returns_the_current_month(): void
    {
        $months = $this->pnl()->monthly();

        $this->assertCount(1, $months);
        $this->assertSame(0, $months->first()['income']);
    }

    // ── صفحه ───────────────────────────────────────────────

    public function test_the_page_is_open_to_finance_and_admin(): void
    {
        foreach ([Role::Finance, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->get(route('reports.profit-and-loss'))->assertOk();
        }
    }

    public function test_a_deputy_cannot_open_the_profit_and_loss(): void
    {
        $this->actingAs(User::factory()->role(Role::Deputy)->create());

        $this->get(route('reports.profit-and-loss'))->assertForbidden();
    }

    public function test_a_teacher_cannot_open_the_profit_and_loss(): void
    {
        $this->actingAs(User::factory()->role(Role::Teacher)->create());

        $this->get(route('reports.profit-and-loss'))->assertForbidden();
    }

    public function test_the_page_shows_the_figures(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $this->income(10_000_000);
        $this->expense(ExpenseCategory::Rent, 4_000_000);

        $this->get(route('reports.profit-and-loss'))
            ->assertOk()
            ->assertSee(to_persian_digits('10,000,000'))
            ->assertSee(to_persian_digits('4,000,000'))
            ->assertSee(to_persian_digits('6,000,000'))
            ->assertSee('سود خالص');
    }

    public function test_the_page_says_zian_when_the_period_loses_money(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $this->income(1_000_000);
        $this->expense(ExpenseCategory::Salary, 5_000_000);

        $this->get(route('reports.profit-and-loss'))
            ->assertOk()
            ->assertSee('زیان خالص')
            ->assertSee(to_persian_digits('4,000,000'));
    }

    public function test_the_page_accepts_a_jalali_range(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $this->income(8_000_000, '2026-03-21');
        $this->expense(ExpenseCategory::Rent, 3_000_000, '2026-05-01');

        $this->get(route('reports.profit-and-loss', ['from' => '۱۴۰۵/۰۱/۰۱', 'to' => '۱۴۰۵/۱۲/۲۹']))
            ->assertOk()
            ->assertSee(to_persian_digits('8,000,000'))
            ->assertSee(to_persian_digits('5,000,000'));
    }

    public function test_the_page_shows_the_categories(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $this->expense(ExpenseCategory::Marketing, 2_000_000);

        $this->get(route('reports.profit-and-loss'))
            ->assertOk()
            ->assertSee('تبلیغات');
    }

    public function test_the_page_shows_the_monthly_trend(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $this->income(3_000_000, '2026-10-05');

        $this->get(route('reports.profit-and-loss', ['from' => '1405/07/01', 'to' => '1405/07/31']))
            ->assertOk()
            ->assertSee(to_persian_digits('1405/07'))
            ->assertSee(to_persian_digits('3,000,000'));
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('reports.profit-and-loss'))->assertRedirect(route('login'));
    }
}
