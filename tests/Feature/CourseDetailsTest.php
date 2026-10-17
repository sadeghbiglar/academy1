<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| جزئیات دوره (شماره 14)
*/

class CourseDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_modal_opens_for_the_selected_course(): void
    {
        $course = Course::factory()->create(['title' => 'دورهٔ قابل مشاهده']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSet('showViewModal', true)
            ->assertSet('viewingCourseId', $course->id)
            ->assertSee('دورهٔ قابل مشاهده');
    }

    public function test_it_shows_the_identity_fields(): void
    {
        $course = Course::factory()->create([
            'title' => 'دورهٔ الف',
            'code' => 'MATH-3',
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('دورهٔ الف')
            ->assertSee('MATH-3')
            ->assertSee($course->subject->name)
            ->assertSee($course->teacher->first_name.' '.$course->teacher->last_name);
    }

    public function test_a_course_without_a_teacher_says_it_is_unassigned(): void
    {
        $course = Course::factory()->withoutTeacher()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('تعیین نشده');
    }

    public function test_it_shows_the_scheduling_fields_in_jalali(): void
    {
        $course = Course::factory()->create([
            'starts_at' => '2026-11-01',
            'session_count' => 12,
            'session_duration' => 90,
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('1405/08/10')
            ->assertSee('۱۲')
            ->assertSee('۹۰')
            ->assertSee('۱۸٫۰', escape: false);   // 12 × 90 / 60
    }

    public function test_it_shows_the_total_hours(): void
    {
        // مدت کل همان چیزی است که آموزشگاه واقعاً برنامه‌ریزی می‌کند
        $course = Course::factory()->create([
            'session_count' => 10,
            'session_duration' => 60,
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('۱۰٫۰', escape: false);
    }

    public function test_it_shows_the_pricing(): void
    {
        $course = Course::factory()->create(['base_fee' => 20_000_000]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee(to_persian_digits('2,000,000').' تومان');
    }

    public function test_a_free_course_is_marked(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', Course::factory()->create(['base_fee' => 0])->id)
            ->assertSee('رایگان');
    }

    public function test_it_lists_the_enrolled_students(): void
    {
        $course = Course::factory()->create();

        $enrollment = CourseEnrollment::enroll(
            Student::factory()->create(['first_name' => 'نگار', 'last_name' => 'موسوی']),
            $course,
        );

        $enrollment->update(['final_fee' => 15_000_000]);
        $enrollment->refresh();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('نگار موسوی')
            ->assertSee('در انتظار تأیید')
            ->assertSee(to_persian_digits('1,500,000').' تومان');
    }

    public function test_an_unset_fee_is_not_shown_as_zero(): void
    {
        // null یعنی «تعیین نشده»، صفر یعنی «رایگان» — یکی گرفتنشان اشتباه مالی است
        $course = Course::factory()->create();

        CourseEnrollment::enroll(Student::factory()->create(), $course);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('تعیین نشده');
    }

    public function test_a_course_with_no_enrolments_says_so(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', Course::factory()->create()->id)
            ->assertSee('هنوز کسی در این دوره ثبت‌نام نکرده است');
    }

    public function test_it_shows_the_capacity_and_free_seats(): void
    {
        $course = Course::factory()->create(['capacity' => 5]);
        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('۱')
            ->assertSee('۴');
    }

    public function test_an_unlimited_course_says_unlimited(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', Course::factory()->create(['capacity' => 0])->id)
            ->assertSee('نامحدود');
    }

    public function test_it_shows_the_status(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', Course::factory()->closed()->create()->id)
            ->assertSee('بسته');
    }

    public function test_closing_resets_the_modal(): void
    {
        $course = Course::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->call('closeView')
            ->assertSet('showViewModal', false)
            ->assertSet('viewingCourseId', null);
    }

    public function test_opening_another_course_replaces_the_current_one(): void
    {
        $first = Course::factory()->create(['title' => 'اولی']);
        $second = Course::factory()->create(['title' => 'دومی']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $first->id)
            ->call('viewCourse', $second->id)
            ->assertSet('viewingCourseId', $second->id)
            ->assertSee('دومی');
    }

    public function test_it_shows_the_subject_annual_capacity(): void
    {
        $course = Course::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('ساعت باقی‌مانده');
    }

    public function test_a_subject_without_an_hour_limit_says_so(): void
    {
        $course = Course::factory()->create();
        $course->subject->update(['annual_hours' => null]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('viewCourse', $course->id)
            ->assertSee('محدودیت ندارد');
    }
}
