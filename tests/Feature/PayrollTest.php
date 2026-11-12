<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Enums\Role;
use App\Models\Payroll;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|---------------------------------------------------------------------------
| حقوق و دستمزد اساتید (شماره 40)
|
| دو چیز باید این‌جا اثبات شود:
|   ۱. محاسبه: خالص = ناخالص − بیمه − مالیات، و خالصِ ذخیره‌شده چیزی نیست
|      که کاربر فرستاده — از بقیهٔ ستون‌ها درست می‌آید. ورودیِ مالی با ارقام
|      فارسی و جداکنندهٔ هزارگان باید به عدد برسد (parse_number)، نه اینکه
|      قاعدهٔ numeric روی «۱۰٬۰۰۰٬۰۰۰» ببازد.
|   ۲. فیش: صفحهٔ چاپی بدون منوی پنل، با سربرگ آموزشگاه، دورهٔ شمسی، امضا.
|
| روزهای کارکرد نباید از روزهای همان ماه شمسی بیشتر باشد — ۳۱ روز کار در
| مهرِ ۳۰ روزه یعنی دادهٔ غلط که در فیش هم چاپ می‌شود.
*/

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): Teacher
    {
        return Teacher::factory()->create();
    }

    private function actingAsRole(Role $role): User
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user);

        return $user;
    }

    /** فرم حقوق را با مقدارهای درست پر می‌کند و ثبت می‌زند. */
    private function store(array $overrides = [])
    {
        $values = array_merge([
            'payTeacher' => (string) $this->teacher()->id,
            'payDate' => miladi_date('۱۴۰۴/۰۸/۱۵'),
            'payDays' => '20',
            'payOvertime' => '6',
            'payGross' => '۱۰٬۰۰۰٬۰۰۰',
            'payInsurance' => '700000',
            'payTax' => '300000',
        ], $overrides);

        $component = Livewire::test('pages::payrolls.index');

        foreach ($values as $field => $value) {
            $component = $component->set($field, $value);
        }

        return $component->call('storePayroll');
    }

    // ── محاسبه ─────────────────────────────────────────────

    public function test_the_net_is_calculated_from_the_gross_and_the_deductions(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->store()->assertHasNoErrors();

        $payroll = Payroll::sole();

        // ناخالص با ارقام فارسی و جداکنندهٔ هزارگان تایپ شد
        $this->assertSame(10_000_000, $payroll->gross);
        $this->assertSame(700_000, $payroll->insurance);
        $this->assertSame(300_000, $payroll->tax);
        // خالص ورودی کاربر نیست: از بقیهٔ ستون‌ها حساب می‌شود
        $this->assertSame(9_000_000, $payroll->net);
        $this->assertSame(20, $payroll->days_worked);
        $this->assertSame(6.0, (float) $payroll->overtime_hours);

        // دوره از تاریخِ شمسیِ انتخاب‌شده می‌آید، نه از تقویم میلادی
        $this->assertSame(1404, $payroll->period_year);
        $this->assertSame(8, $payroll->period_month);
        $this->assertSame(PayrollStatus::Pending, $payroll->status);
    }

    public function test_the_deductions_cannot_exceed_the_gross(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->store([
            'payGross' => '1000000',
            'payInsurance' => '800000',
            'payTax' => '300000',
        ])->assertHasErrors(['payInsurance']);

        $this->assertSame(0, Payroll::count());
    }

    public function test_days_cannot_exceed_the_days_of_that_jalali_month(): void
    {
        $this->actingAsRole(Role::Admin);

        // مهر ۳۰ روزه است؛ ۳۱ روز کارکرد در آن ماه ناممکن است
        $this->store([
            'payDate' => miladi_date('۱۴۰۴/۰۷/۱۰'),
            'payDays' => '31',
        ])->assertHasErrors(['payDays']);

        $this->assertSame(0, Payroll::count());
    }

    public function test_a_teacher_is_paid_once_per_month(): void
    {
        $this->actingAsRole(Role::Admin);
        $teacher = $this->teacher();

        $this->store(['payTeacher' => (string) $teacher->id])->assertHasNoErrors();

        // دوباره زدنِ ثبت، فیش دوم برای یک ماه نمی‌سازد — وگرنه حقوق دو بار
        // پرداخت می‌شود و گزارش ماهانه دو ردیف نشان می‌دهد
        $this->store(['payTeacher' => (string) $teacher->id])->assertHasErrors(['payTeacher']);

        $this->assertSame(1, Payroll::count());
    }

    public function test_marking_a_payroll_as_paid_records_the_date(): void
    {
        $this->actingAsRole(Role::Admin);
        $teacher = $this->teacher();

        $this->store(['payTeacher' => (string) $teacher->id])->assertHasNoErrors();

        Livewire::test('pages::payrolls.index')
            ->call('markPaid', Payroll::sole()->id)
            ->assertHasNoErrors();

        $payroll = Payroll::sole();

        $this->assertSame(PayrollStatus::Paid, $payroll->status);
        // تاریخ پرداخت میلادی ذخیره می‌شود؛ نمایشش شمسی خواهد بود
        $this->assertSame(now()->toDateString(), $payroll->paid_at->toDateString());
    }

    // ── فیش حقوقی ─────────────────────────────────────────

    public function test_the_payslip_is_generated_without_the_panel_chrome(): void
    {
        $this->actingAsRole(Role::Admin);
        $teacher = $this->teacher();

        $this->store(['payTeacher' => (string) $teacher->id])->assertHasNoErrors();

        $response = $this->get(route('payroll.print', Payroll::sole()));

        $response->assertOk();
        // منو و سایهٔ پنل روی کاغذ نباید باشند
        $response->assertDontSee('drawer');

        $response->assertSee('فیش حقوقی')
            ->assertSee($teacher->first_name)
            // دوره به شمسی: «آبان ۱۴۰۴»
            ->assertSee(jalali_month_name(8).' '.to_persian_digits(1404))
            ->assertSee(to_persian_digits(number_format(Payroll::sole()->net)))
            ->assertSee('امضای مدیر آموزشگاه');
    }

    public function test_the_payslip_shows_the_status_of_the_payment(): void
    {
        $this->actingAsRole(Role::Deputy);
        $teacher = $this->teacher();

        $this->store(['payTeacher' => (string) $teacher->id])->assertHasNoErrors();

        $this->get(route('payroll.print', Payroll::sole()))
            ->assertOk()
            ->assertSee('در انتظار پرداخت');
    }

    // ── دسترسی ────────────────────────────────────────────

    public function test_finance_and_reception_cannot_open_payrolls(): void
    {
        foreach ([Role::Finance, Role::Reception, Role::Teacher] as $role) {
            $this->actingAsRole($role);

            $this->get('/payrolls')->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/payrolls')->assertRedirect(route('login'));
    }

    public function test_the_page_renders_for_the_admin(): void
    {
        $this->actingAsRole(Role::Admin);

        $this->get('/payrolls')
            ->assertOk()
            ->assertSee('حقوق و دستمزد اساتید')
            ->assertSee('صدور فیش');
    }
}
