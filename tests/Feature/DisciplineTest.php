<?php

namespace Tests\Feature;

use App\Enums\DisciplineType;
use App\Enums\Role;
use App\Models\Discipline;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|---------------------------------------------------------------------------
| ثبت رفتار و انضباط دانش‌آموز (شماره 36)
|
| سه چیز باید اینجا اثبات شود، نه فقط «ردیف ساخته می‌شود»:
|   ۱. ثبت‌کننده و تاریخ: تاریخی که کاربر می‌بیند شمسی است ولی باید میلادی
|      ذخیره شود، وگرنه بازهٔ گزارش مرتب نمی‌شود.
|   ۲. نمایش در پرونده با همان تاریخ شمسی، به‌علاوهٔ نام ثبت‌کننده.
|   ۳. فیلتر بازهٔ گزارش: سرها باید شامل باشند و رکورد بیرون از بازه نباید
|      دیده شود — این دقیقاً همان چیزی است که تاریخ میلادی در DB برایش لازم است.
|
| نقش‌هایی که انضباط را می‌بینند همان نقش‌های بخش آموزشی‌اند؛ امور مالی و
| پذیرش پرونده را باز می‌کنند ولی کاری به رفتار دانش‌آموز ندارند.
*/

class DisciplineTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        return Student::factory()->create(['first_name' => 'زهرا', 'last_name' => 'کریمی']);
    }

    private function actingAsRole(Role $role): User
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user);

        return $user;
    }

    // ── ثبت ───────────────────────────────────────────────

    public function test_an_entry_is_recorded_with_its_registrar_and_a_gregorian_date(): void
    {
        $user = $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('disciplineType', 'late')
            ->set('disciplineNote', 'با تأخیر وارد کلاس شد')
            ->set('disciplineDate', '2026-11-02')
            ->call('storeDiscipline')
            ->assertHasNoErrors();

        $record = Discipline::sole();

        $this->assertModelExists($record);
        $this->assertSame(DisciplineType::Late, $record->type);
        $this->assertSame($student->id, $record->student_id);
        $this->assertSame($user->id, $record->user_id);
        // در DB میلادی، نه شمسی
        $this->assertSame('2026-11-02', $record->occurred_on->toDateString());
    }

    public function test_a_shamsi_date_typed_by_the_user_is_stored_as_gregorian(): void
    {
        $this->actingAsRole(Role::Deputy);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('disciplineType', 'warning')
            ->set('disciplineNote', 'تذکر در مورد تکلیف')
            ->set('disciplineDate', '۱۴۰۴/۰۸/۱۱')
            ->call('storeDiscipline')
            ->assertHasNoErrors();

        $this->assertSame(
            miladi_date('۱۴۰۴/۰۸/۱۱'),
            Discipline::sole()->occurred_on->toDateString(),
        );
    }

    public function test_an_impossible_shamsi_date_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('disciplineType', 'warning')
            ->set('disciplineNote', 'تذکر')
            // آبان ۳۱ روز دارد؛ روز ۳۲ وجود ندارد و نباید به ماه بعد غلت کند
            ->set('disciplineDate', '۱۴۰۴/۰۸/۳۲')
            ->call('storeDiscipline')
            ->assertHasErrors(['disciplineDate']);

        $this->assertSame(0, Discipline::count());
    }

    public function test_an_empty_description_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('disciplineType', 'warning')
            ->set('disciplineNote', '')
            ->set('disciplineDate', '2026-11-02')
            ->call('storeDiscipline')
            ->assertHasErrors(['disciplineNote']);

        $this->assertSame(0, Discipline::count());
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('disciplineType', 'insult')
            ->set('disciplineNote', 'شرحی')
            ->set('disciplineDate', '2026-11-02')
            ->call('storeDiscipline')
            ->assertHasErrors(['disciplineType']);

        $this->assertSame(0, Discipline::count());
    }

    public function test_a_role_without_academic_access_cannot_record(): void
    {
        // پذیرش پرونده را می‌بیند، ولی ثبت انضباط کار او نیست — پس دکمهٔ
        // پنهان باید سمت سرور هم بسته باشد، وگرنه با URL مستقیم دور زده می‌شود
        $this->actingAsRole(Role::Reception);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('disciplineType', 'warning')
            ->set('disciplineNote', 'شرحی')
            ->set('disciplineDate', '2026-11-02')
            ->call('storeDiscipline')
            ->assertForbidden();

        $this->assertSame(0, Discipline::count());
    }

    // ── نمایش در پرونده ──────────────────────────────────

    public function test_the_profile_shows_the_entry_with_a_shamsi_date_and_the_registrar(): void
    {
        $user = $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Discipline::factory()->create([
            'student_id' => $student->id,
            'user_id' => $user->id,
            'type' => DisciplineType::Praise,
            'description' => 'در کلاس مشارکت فعال داشت',
            'occurred_on' => '2026-11-02',
        ]);

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('تقدیر')
            ->assertSee('در کلاس مشارکت فعال داشت')
            // نمایش شمسی و فارسی، نه میلادی خام
            ->assertSee(to_persian_digits(jalali_date('2026-11-02')))
            ->assertSee($user->display_name);
    }

    public function test_the_profile_shows_nothing_when_nothing_is_recorded(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('هنوز رخدادی ثبت نشده است.');
    }

    public function test_the_finance_role_does_not_see_the_discipline_card(): void
    {
        $this->actingAsRole(Role::Finance);
        $student = $this->student();

        Discipline::factory()->create([
            'student_id' => $student->id,
            'type' => DisciplineType::Warning,
        ]);

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertDontSee('انضباط و رفتار')
            ->assertDontSee('تذکر');
    }

    // ── گزارش بازه‌ای ────────────────────────────────────

    public function test_the_report_filters_by_a_shamsi_range(): void
    {
        $user = $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Discipline::factory()->create([
            'student_id' => $student->id, 'user_id' => $user->id,
            'occurred_on' => '2026-11-02', 'description' => 'درون بازهٔ نخست',
        ]);
        Discipline::factory()->create([
            'student_id' => $student->id, 'user_id' => $user->id,
            // لبهٔ پایان بازه باید شامل شود، نه حذف
            'occurred_on' => '2026-11-30', 'description' => 'درون بازهٔ دوم',
        ]);
        Discipline::factory()->create([
            'student_id' => $student->id, 'user_id' => $user->id,
            'occurred_on' => '2026-12-15', 'description' => 'بیرون از بازه',
        ]);

        $from = jalali_date('2026-11-01');
        $to = jalali_date('2026-11-30');

        $response = $this->get('/reports/discipline?from='.urlencode($from).'&to='.urlencode($to));

        $response->assertOk();
        $response->assertSee('درون بازهٔ نخست');
        $response->assertSee('درون بازهٔ دوم');
        $response->assertDontSee('بیرون از بازه');
        // خود گزارش هم شمسی نشان می‌دهد
        $response->assertSee(to_persian_digits(jalali_date('2026-11-02')));
        $response->assertSee($student->first_name);
        $response->assertSee($user->display_name);
    }

    public function test_the_report_without_a_filter_shows_everything(): void
    {
        $this->actingAsRole(Role::Deputy);
        $student = $this->student();

        Discipline::factory()->create([
            'student_id' => $student->id,
            'occurred_on' => '2026-12-15',
            'description' => 'رخداد زمستانی',
        ]);

        $this->get('/reports/discipline')
            ->assertOk()
            ->assertSee('رخداد زمستانی');
    }

    public function test_the_report_is_forbidden_to_finance_and_reception(): void
    {
        foreach ([Role::Finance, Role::Reception] as $role) {
            $this->actingAsRole($role);

            $this->get('/reports/discipline')->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/reports/discipline')->assertRedirect(route('login'));
    }
}
