<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Course;
use App\Models\Discount;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| تخفیف‌ها (شماره 53)
*/

class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private function discount(array $attributes = []): Discount
    {
        return Discount::create(array_merge([
            'title' => 'تخفیف خواهران',
            'type' => DiscountType::Percent,
            'value' => 10,
        ], $attributes));
    }

    // ── نوع و مقدار ───────────────────────────────────────

    public function test_a_percentage_discount_takes_a_share_of_the_amount(): void
    {
        $this->assertSame(200_000, $this->discount(['value' => 10])->amountOn(2_000_000));
    }

    public function test_a_fixed_discount_takes_a_flat_amount(): void
    {
        $discount = $this->discount(['type' => DiscountType::Fixed, 'value' => 500_000]);

        $this->assertSame(500_000, $discount->amountOn(2_000_000));
    }

    public function test_a_zero_discount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->discount(['value' => 0]);
    }

    public function test_a_percentage_above_one_hundred_is_rejected(): void
    {
        // ۱۲۰٪ فاکتور را منفی می‌کند
        $this->expectException(ValidationException::class);

        $this->discount(['value' => 120]);
    }

    public function test_a_hundred_percent_discount_is_allowed(): void
    {
        $discount = $this->discount(['value' => 100]);

        $this->assertSame(1_000_000, $discount->amountOn(1_000_000));
    }

    public function test_a_fixed_discount_above_the_amount_stops_at_the_amount(): void
    {
        $discount = $this->discount(['type' => DiscountType::Fixed, 'value' => 5_000_000]);

        $this->assertSame(1_000_000, $discount->amountOn(1_000_000));
    }

    public function test_a_discount_never_becomes_negative(): void
    {
        // ۲۰۰٪ روی مبلغی کوچک‌تر: نه منفی، نه بیشتر از خود مبلغ
        $discount = $this->discount(['value' => 100]);

        $this->assertSame(0, $discount->amountOn(0));
    }

    // ── دامنه ─────────────────────────────────────────────

    public function test_a_global_discount_applies_to_anyone(): void
    {
        $discount = $this->discount();

        $this->assertTrue($discount->appliesTo(Student::factory()->create(), Course::factory()->create()));
        $this->assertSame('همه', $discount->scopeLabel());
    }

    public function test_a_student_scoped_discount_only_applies_to_that_student(): void
    {
        $student = Student::factory()->create();
        $other = Student::factory()->create();

        $discount = $this->discount(['student_id' => $student->id]);

        $this->assertTrue($discount->appliesTo($student, null));
        $this->assertFalse($discount->appliesTo($other, null));
        $this->assertSame('یک دانش‌آموز', $discount->scopeLabel());
    }

    public function test_a_course_scoped_discount_only_applies_to_that_course(): void
    {
        $course = Course::factory()->create();
        $other = Course::factory()->create();

        $discount = $this->discount(['course_id' => $course->id]);

        $this->assertTrue($discount->appliesTo(null, $course));
        $this->assertFalse($discount->appliesTo(null, $other));
        $this->assertSame('یک دوره', $discount->scopeLabel());
    }

    public function test_a_discount_scoped_to_both_needs_both_to_match(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $discount = $this->discount(['student_id' => $student->id, 'course_id' => $course->id]);

        $this->assertTrue($discount->appliesTo($student, $course));
        $this->assertFalse($discount->appliesTo($student, Course::factory()->create()));
        $this->assertSame('دانش‌آموز و دوره', $discount->scopeLabel());
    }

    public function test_the_available_discounts_match_the_invoice_scope(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $this->discount(['title' => 'همه']);
        $this->discount(['title' => 'این دانش‌آموز', 'student_id' => $student->id]);
        $this->discount(['title' => 'دانش‌آموز دیگر', 'student_id' => Student::factory()->create()->id]);
        $this->discount(['title' => 'این دوره', 'course_id' => $course->id]);
        $this->discount(['title' => 'دورهٔ دیگر', 'course_id' => Course::factory()->create()->id]);

        $titles = Discount::availableFor($student, $course)->pluck('title')->all();

        $this->assertContains('همه', $titles);
        $this->assertContains('این دانش‌آموز', $titles);
        $this->assertContains('این دوره', $titles);
        $this->assertNotContains('دانش‌آموز دیگر', $titles);
        $this->assertNotContains('دورهٔ دیگر', $titles);
    }

    // ── انقضا ─────────────────────────────────────────────

    public function test_a_discount_without_dates_is_always_active(): void
    {
        $this->assertTrue($this->discount()->isActive());
    }

    public function test_an_expired_discount_is_inactive(): void
    {
        $discount = $this->discount(['expires_at' => '2026-10-01']);

        $this->assertFalse($discount->isActive('2026-11-01'));
        $this->assertTrue($discount->isExpired('2026-11-01'));
    }

    public function test_a_discount_is_active_on_its_last_day(): void
    {
        // «تا ۳۱ خرداد» باید در خودِ ۳۱ خرداد هم اعمال شود
        $discount = $this->discount(['expires_at' => '2026-10-01']);

        $this->assertTrue($discount->isActive('2026-10-01'));
    }

    public function test_a_discount_is_active_on_its_first_day(): void
    {
        $discount = $this->discount(['starts_at' => '2026-10-01']);

        $this->assertTrue($discount->isActive('2026-10-01'));
    }

    public function test_a_future_discount_is_not_active_yet(): void
    {
        $discount = $this->discount(['starts_at' => '2026-12-01']);

        $this->assertFalse($discount->isActive('2026-10-01'));
        $this->assertTrue($discount->isScheduled('2026-10-01'));
    }

    public function test_an_expiry_before_the_start_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->discount(['starts_at' => '2026-12-01', 'expires_at' => '2026-10-01']);
    }

    public function test_the_active_scope_excludes_expired_discounts(): void
    {
        $this->discount(['title' => 'معتبر']);
        $this->discount(['title' => 'منقضی', 'expires_at' => '2026-10-01']);

        $titles = Discount::active('2026-11-01')->pluck('title')->all();

        $this->assertSame(['معتبر'], $titles);
    }

    public function test_an_expired_discount_is_not_offered(): void
    {
        $this->discount(['title' => 'منقضی', 'expires_at' => '2026-10-01']);

        $this->assertCount(0, Discount::availableFor(Student::factory()->create(), null, '2026-11-01'));
    }

    // ── ترکیب ──────────────────────────────────────────────

    public function test_two_discounts_combine_sequentially(): void
    {
        // ۱۰٪ و بعد ۲۰۰ هزار روی ۲ میلیون
        $discounts = [
            $this->discount(['type' => DiscountType::Percent, 'value' => 10]),
            $this->discount(['type' => DiscountType::Fixed, 'value' => 200_000]),
        ];

        // ۱۰٪ از ۲ میلیون = ۲۰۰ هزار، باقی‌مانده ۱٫۸ میلیون، منهای ۲۰۰ هزار
        $this->assertSame(400_000, Discount::combinedAmount($discounts, 2_000_000));
    }

    public function test_the_combined_discount_cannot_exceed_the_amount(): void
    {
        $discounts = [
            $this->discount(['type' => DiscountType::Percent, 'value' => 100]),
            $this->discount(['type' => DiscountType::Fixed, 'value' => 500_000]),
        ];

        $this->assertSame(1_000_000, Discount::combinedAmount($discounts, 1_000_000));
    }

    public function test_no_discounts_leave_the_amount_untouched(): void
    {
        $this->assertSame(0, Discount::combinedAmount([], 2_000_000));
    }

    // ── اعمال روی فاکتور ───────────────────────────────────

    public function test_a_single_discount_is_applied_to_the_invoice(): void
    {
        $student = Student::factory()->create();

        $invoice = Invoice::issueWithDiscounts(
            $student,
            null,
            2_000_000,
            [$this->discount(['value' => 10])],
        );

        $this->assertSame(200_000, $invoice->discount_amount);
        $this->assertSame(1_800_000, $invoice->payable);
    }

    public function test_combined_discounts_are_stored_as_their_total(): void
    {
        // ریز ترکیب نباید در چاپ به یک تخفیف تقلیل پیدا کند
        $student = Student::factory()->create();

        $invoice = Invoice::issueWithDiscounts($student, null, 2_000_000, [
            $this->discount(['type' => DiscountType::Percent, 'value' => 10]),
            $this->discount(['type' => DiscountType::Fixed, 'value' => 200_000]),
        ]);

        $this->assertSame(400_000, $invoice->discount_amount);
        $this->assertSame(1_600_000, $invoice->payable);
    }

    public function test_a_mixed_combination_is_displayed_as_a_fixed_amount(): void
    {
        // نوعِ نمایش باید با حساب واقعی بخواند
        $student = Student::factory()->create();

        $invoice = Invoice::issueWithDiscounts($student, null, 2_000_000, [
            $this->discount(['type' => DiscountType::Percent, 'value' => 10]),
            $this->discount(['type' => DiscountType::Fixed, 'value' => 200_000]),
        ]);

        $this->assertSame(DiscountType::Fixed, $invoice->discount_type);
        $this->assertSame(400_000, $invoice->discount_value);
    }

    public function test_a_percentage_only_combination_keeps_its_type(): void
    {
        $student = Student::factory()->create();

        $invoice = Invoice::issueWithDiscounts($student, null, 2_000_000, [
            $this->discount(['type' => DiscountType::Percent, 'value' => 10]),
        ]);

        $this->assertSame(DiscountType::Percent, $invoice->discount_type);
    }

    public function test_no_discount_list_issues_a_plain_invoice(): void
    {
        $invoice = Invoice::issueWithDiscounts(Student::factory()->create(), null, 2_000_000, []);

        $this->assertSame(0, $invoice->discount_amount);
        $this->assertSame(2_000_000, $invoice->payable);
    }

    public function test_an_over_discount_leaves_the_invoice_at_zero_not_negative(): void
    {
        $student = Student::factory()->create();

        $invoice = Invoice::issueWithDiscounts($student, null, 500_000, [
            $this->discount(['type' => DiscountType::Fixed, 'value' => 900_000]),
        ]);

        $this->assertSame(500_000, $invoice->discount_amount);
        $this->assertSame(0, $invoice->payable);
        $this->assertFalse($invoice->isPaid());
    }

    public function test_a_discount_rule_changed_later_does_not_move_an_old_invoice(): void
    {
        // فاکتور سند است؛ قاعدهٔ تخفیف بعداً نباید آن را جابه‌جا کند
        $student = Student::factory()->create();
        $rule = $this->discount(['value' => 10]);

        $invoice = Invoice::issueWithDiscounts($student, null, 2_000_000, [$rule]);

        $rule->update(['value' => 50]);

        $this->assertSame(200_000, $invoice->fresh()->discount_amount);
        $this->assertSame(1_800_000, $invoice->fresh()->payable);
    }

    // ── نمایش و روابط ──────────────────────────────────────

    public function test_the_value_is_shown_in_persian(): void
    {
        $this->assertSame(to_persian_digits('10').'٪', $this->discount(['value' => 10])->valueLabel());
        $this->assertSame(
            to_persian_digits('500,000').' تومان',
            $this->discount(['type' => DiscountType::Fixed, 'value' => 500_000])->valueLabel(),
        );
    }

    public function test_the_expiry_is_shown_in_jalali(): void
    {
        $discount = $this->discount(['expires_at' => '2026-11-01']);

        $this->assertSame('1405/08/10', $discount->expiresAtJalali());
        $this->assertNull($this->discount()->expiresAtJalali());
    }

    public function test_deleting_a_discount_leaves_an_applied_invoice_intact(): void
    {
        $student = Student::factory()->create();
        $rule = $this->discount(['value' => 10]);
        $invoice = Invoice::issueWithDiscounts($student, null, 2_000_000, [$rule]);

        $rule->delete();

        $this->assertModelExists($invoice->fresh());
        $this->assertSame(1_800_000, $invoice->fresh()->payable);
    }

    public function test_the_discount_belongs_to_its_scope(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $discount = $this->discount(['student_id' => $student->id, 'course_id' => $course->id]);

        $this->assertSame($student->id, $discount->student->id);
        $this->assertSame($course->id, $discount->course->id);
    }
}
