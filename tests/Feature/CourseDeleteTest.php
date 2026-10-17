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
| حذف دوره با تأیید (شماره 13)
|--------------------------------------------------------------------------
| تصمیم ثبت‌شده در مدل: دوره‌ای که ثبت‌نام قطعی دارد حذف نمی‌شود. دلیلش این
| است که همان ثبت‌نام‌ها مبنای فاکتور شهریه و کارنامه‌اند و حذف والد آن‌ها را
| بی‌صدا می‌برد.
*/

class CourseDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_confirmation_modal_names_the_course(): void
    {
        $course = Course::factory()->create(['title' => 'دورهٔ قابل حذف']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('confirmDelete', $course->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingCourseTitle', 'دورهٔ قابل حذف')
            ->assertSee('دورهٔ قابل حذف');
    }

    public function test_a_course_without_confirmed_enrolments_is_deleted(): void
    {
        $course = Course::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('confirmDelete', $course->id)
            ->call('deleteCourse')
            ->assertSet('showDeleteModal', false);

        $this->assertNull(Course::find($course->id));
    }

    public function test_a_course_with_confirmed_enrolments_is_not_deletable(): void
    {
        $course = Course::factory()->create();
        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        $this->assertFalse($course->isDeletable());
        $this->assertSame(1, $course->confirmedEnrollmentsCount());
    }

    public function test_the_modal_warns_instead_of_deleting_when_enrolments_exist(): void
    {
        $course = Course::factory()->create(['title' => 'دورهٔ شلوغ']);
        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('confirmDelete', $course->id)
            ->assertSet('deletingCourseEnrollments', 1)
            ->assertSee('بایگانی');
    }

    public function test_cancelled_and_pending_enrolments_do_not_block_deletion(): void
    {
        // فقط ثبت‌نام قطعی مانع است؛ رزرو و لغو شده سابقه‌ای ندارند
        $course = Course::factory()->create();

        CourseEnrollment::enroll(Student::factory()->create(), $course);
        CourseEnrollment::enroll(Student::factory()->create(), $course)->cancel();

        $this->assertTrue($course->isDeletable());
    }

    public function test_waitlisted_enrolments_do_not_block_deletion(): void
    {
        $course = Course::factory()->create(['capacity' => 1]);

        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();
        CourseEnrollment::enroll(Student::factory()->create(), $course);

        $this->assertFalse($course->isDeletable());
    }

    public function test_deleting_removes_the_remaining_enrolments_too(): void
    {
        $course = Course::factory()->create();

        CourseEnrollment::enroll(Student::factory()->create(), $course);
        CourseEnrollment::enroll(Student::factory()->create(), $course);

        $course->delete();

        $this->assertSame(0, CourseEnrollment::query()->count());
    }

    public function test_cancelling_the_confirmation_deletes_nothing(): void
    {
        $course = Course::factory()->create(['title' => 'می‌ماند']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('confirmDelete', $course->id)
            ->call('cancelDelete')
            ->assertSet('showDeleteModal', false)
            ->assertSet('deletingCourseId', null)
            ->assertSet('deletingCourseTitle', '');

        $this->assertNotNull(Course::find($course->id));
    }

    public function test_opening_another_course_replaces_the_pending_one(): void
    {
        // وگرنه کاربر نام دورهٔ اشتباه را تأیید می‌کند
        $first = Course::factory()->create(['title' => 'اولی']);
        $second = Course::factory()->create(['title' => 'دومی']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('confirmDelete', $first->id)
            ->call('confirmDelete', $second->id)
            ->assertSet('deletingCourseTitle', 'دومی');
    }

    public function test_archived_courses_can_still_be_deleted_when_empty(): void
    {
        $course = Course::factory()->archived()->create();

        $this->assertTrue($course->isDeletable());

        $course->delete();

        $this->assertNull(Course::find($course->id));
    }
}
