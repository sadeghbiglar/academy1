<?php

namespace Tests\Feature;

use App\Enums\PreviousStatus;
use App\Enums\Role;
use App\Models\PreviousEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|---------------------------------------------------------------------------
| رونویس و سوابق تحصیلی قبلی (شماره 38)
|
| چیزی که این‌جا باید اثبات شود:
|   ۱. ثبت: «سال» با ارقام فارسی تایپ می‌شود و باید به عدد صحیح برسد
|      (parse_integer)، نه اینکه رشته «۱۴۰۳» در ستون integer بنشیند یا رد شود.
|   ۲. نمایش در پرونده با ارقام فارسی و برچسب فارسی وضعیت.
|   ۳. همان قاعدهٔ دسترسی انضباط: پرونده برای امور مالی و پذیرش باز می‌شود
|      ولی سوابق تحصیلی کار آن‌ها نیست.
|
| سال، برخلاف تاریخ، عمداً شمسی ذخیره می‌شود: «۱۴۰۳» برچسبِ سالِ تحصیلی است
| نه یک روز از تقویم، و مقایسهٔ روزبه‌روزی رویش معنا ندارد.
*/

class PreviousEnrollmentTest extends TestCase
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

    public function test_a_previous_school_record_is_saved(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', 'دبستان شهید فهمیده')
            ->set('prevYear', '۱۴۰۳')
            ->set('prevGrade', '6')
            ->set('prevGrades', 'ریاضی ۱۸، علوم ۱۷')
            ->set('prevStatus', 'graduated')
            ->call('storePreviousEnrollment')
            ->assertHasNoErrors();

        $record = PreviousEnrollment::sole();

        $this->assertModelExists($record);
        $this->assertSame($student->id, $record->student_id);
        // سالِ شمسیِ تایپ‌شده باید عدد صحیح ذخیره شود، نه رشتهٔ ارقام فارسی
        $this->assertSame(1403, $record->academic_year);
        $this->assertSame('6', $record->grade);
        $this->assertSame(PreviousStatus::Graduated, $record->status);
        $this->assertSame('دبستان شهید فهمیده', $record->school);
    }

    public function test_an_empty_grade_column_is_allowed(): void
    {
        // کارنامهٔ انتقالی بعضی مدارس همراه نیست؛ نباید ثبت کل رونویس را بگیرد
        $this->actingAsRole(Role::Deputy);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', 'دبیرستان علامه طباطبایی')
            ->set('prevYear', '1404')
            ->set('prevGrade', '10')
            ->set('prevGrades', '')
            ->set('prevStatus', 'continuing')
            ->call('storePreviousEnrollment')
            ->assertHasNoErrors();

        $this->assertNull(PreviousEnrollment::sole()->previous_grades);
    }

    public function test_an_empty_school_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', '')
            ->set('prevYear', '۱۴۰۳')
            ->set('prevGrade', '6')
            ->set('prevStatus', 'graduated')
            ->call('storePreviousEnrollment')
            ->assertHasErrors(['prevSchool']);

        $this->assertSame(0, PreviousEnrollment::count());
    }

    public function test_a_non_numeric_year_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', 'دبستان نمونه')
            // عدد نیست؛ نباید بی‌صدا صفر یا خالی شود
            ->set('prevYear', 'نامشخص')
            ->set('prevGrade', '6')
            ->set('prevStatus', 'graduated')
            ->call('storePreviousEnrollment')
            ->assertHasErrors(['prevYear']);

        $this->assertSame(0, PreviousEnrollment::count());
    }

    public function test_a_year_outside_the_plausible_range_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', 'دبستان نمونه')
            ->set('prevYear', '140')
            ->set('prevGrade', '6')
            ->set('prevStatus', 'graduated')
            ->call('storePreviousEnrollment')
            ->assertHasErrors(['prevYear']);

        $this->assertSame(0, PreviousEnrollment::count());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', 'دبستان نمونه')
            ->set('prevYear', '۱۴۰۳')
            ->set('prevGrade', '6')
            ->set('prevStatus', 'unknown-status')
            ->call('storePreviousEnrollment')
            ->assertHasErrors(['prevStatus']);

        $this->assertSame(0, PreviousEnrollment::count());
    }

    public function test_a_role_without_academic_access_cannot_record(): void
    {
        $this->actingAsRole(Role::Reception);
        $student = $this->student();

        Livewire::test('pages::students.show', ['student' => $student->id])
            ->set('prevSchool', 'دبستان نمونه')
            ->set('prevYear', '۱۴۰۳')
            ->set('prevGrade', '6')
            ->set('prevStatus', 'graduated')
            ->call('storePreviousEnrollment')
            ->assertForbidden();

        $this->assertSame(0, PreviousEnrollment::count());
    }

    // ── نمایش در پرونده ──────────────────────────────────

    public function test_the_profile_shows_the_record_in_persian(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        PreviousEnrollment::factory()->create([
            'student_id' => $student->id,
            'school' => 'دبستان شهید فهمیده',
            'academic_year' => 1403,
            'grade' => '6',
            'previous_grades' => 'ریاضی ۱۸',
            'status' => PreviousStatus::Graduated,
        ]);

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('دبستان شهید فهمیده')
            ->assertSee('۱۴۰۳')                          // سال به ارقام فارسی
            ->assertSee('پایهٔ ششم')                     // پایه با برچسب فارسی
            ->assertSee('فارغ‌التحصیل')                  // وضعیت با برچسب فارسی
            ->assertSee('ریاضی ۱۸');
    }

    public function test_the_profile_shows_nothing_when_there_is_no_record(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = $this->student();

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('سوابق تحصیلی قبلی ثبت نشده است.');
    }

    public function test_the_finance_role_does_not_see_the_previous_school_card(): void
    {
        $this->actingAsRole(Role::Finance);
        $student = $this->student();

        PreviousEnrollment::factory()->create(['student_id' => $student->id]);

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertDontSee('سوابق تحصیلی قبلی');
    }
}
