<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| ویرایش دوره (شماره 12)
*/

class CourseEditFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_form_is_prefilled_from_the_course(): void
    {
        $course = Course::factory()->create([
            'title' => 'دورهٔ اصلی',
            'code' => 'MATH-9',
            'capacity' => 20,
            'session_count' => 16,
            'session_duration' => 75,
            'base_fee' => 4_500_000,
            'starts_at' => '2026-11-01',
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->assertSet('editingCourseId', $course->id)
            ->assertSet('new_title', 'دورهٔ اصلی')
            ->assertSet('new_code', 'MATH-9')
            ->assertSet('new_capacity', '20')
            ->assertSet('new_session_count', '16')
            ->assertSet('new_session_duration', '75')
            ->assertSet('new_base_fee', '4500000')
            ->assertSet('new_subject_id', (string) $course->subject_id);
    }

    public function test_the_start_date_is_prefilled_as_gregorian_iso(): void
    {
        // انتخابگر تاریخ خودش نمایش شمسی را از این می‌سازد؛ پر کردن دستی با
        // تاریخ شمسی باعث می‌شود مقدار ذخیره‌شده ۲۰۲۶ نشود
        $course = Course::factory()->create(['starts_at' => '2026-11-01']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->assertSet('new_starts_at', '2026-11-01');
    }

    public function test_a_course_without_a_start_date_prefills_blank(): void
    {
        $course = Course::factory()->create(['starts_at' => null]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->assertSet('new_starts_at', '');
    }

    public function test_a_course_without_a_teacher_prefills_blank(): void
    {
        $course = Course::factory()->withoutTeacher()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->assertSet('new_teacher_id', '');
    }

    public function test_editing_updates_the_course(): void
    {
        $course = Course::factory()->create(['title' => 'قدیمی']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_title', 'جدید')
            ->set('new_capacity', '30')
            ->set('new_base_fee', '6000000')
            ->call('updateCourse')
            ->assertHasNoErrors();

        $fresh = $course->fresh();

        $this->assertSame('جدید', $fresh->title);
        $this->assertSame(30, $fresh->capacity);
        $this->assertSame(6_000_000, $fresh->base_fee);
    }

    public function test_a_course_can_be_reassigned_to_another_teacher(): void
    {
        $course = Course::factory()->create();
        $teacher = Teacher::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_teacher_id', (string) $teacher->id)
            ->call('updateCourse')
            ->assertHasNoErrors();

        $this->assertSame($teacher->id, $course->fresh()->teacher_id);
    }

    public function test_a_teacher_can_be_removed_from_a_course(): void
    {
        $course = Course::factory()->create(['teacher_id' => Teacher::factory()->create()]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_teacher_id', '')
            ->call('updateCourse')
            ->assertHasNoErrors();

        $this->assertNull($course->fresh()->teacher_id);
    }

    public function test_keeping_the_same_code_does_not_fail_validation(): void
    {
        // بدون ignore، ویرایش هر دوره با خطای «کد تکراری» می‌خورد چون کد با
        // خودِ رکورد در تضاد است
        $course = Course::factory()->create(['code' => 'MATH-7']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->call('updateCourse')
            ->assertHasNoErrors();
    }

    public function test_taking_another_courses_code_is_rejected(): void
    {
        Course::factory()->create(['code' => 'MATH-7']);
        $course = Course::factory()->create(['code' => 'MATH-8']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_code', 'MATH-7')
            ->call('updateCourse')
            ->assertHasErrors('new_code');

        $this->assertSame('MATH-8', $course->fresh()->code);
    }

    public function test_clearing_the_code_stores_null(): void
    {
        $course = Course::factory()->create(['code' => 'MATH-7']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_code', '')
            ->call('updateCourse')
            ->assertHasNoErrors();

        $this->assertNull($course->fresh()->code);
    }

    public function test_the_same_rules_apply_as_on_creation(): void
    {
        $course = Course::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_capacity', '-1')
            ->call('updateCourse')
            ->assertHasErrors('new_capacity');

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_base_fee', '-1')
            ->call('updateCourse')
            ->assertHasErrors('new_base_fee');

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_subject_id', '')
            ->call('updateCourse')
            ->assertHasErrors('new_subject_id');
    }

    public function test_the_form_resets_after_saving(): void
    {
        // باقی ماندن مقادیر، ویرایش بعدی را با دادهٔ دورهٔ قبلی انجام می‌داد
        $course = Course::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_title', 'تغییر یافته')
            ->call('updateCourse')
            ->assertSet('new_title', '')
            ->assertSet('editingCourseId', null)
            ->assertSet('showEditModal', false);
    }

    public function test_cancelling_an_edit_leaves_the_course_untouched(): void
    {
        $course = Course::factory()->create(['title' => 'دست‌نخورده']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_title', 'نیم‌کاره')
            ->call('cancelEdit')
            ->assertSet('editingCourseId', null)
            ->assertSet('showEditModal', false);

        $this->assertSame('دست‌نخورده', $course->fresh()->title);
    }

    public function test_cancelling_clears_stale_validation_messages(): void
    {
        $course = Course::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_title', '')
            ->call('updateCourse')
            ->assertHasErrors('new_title')
            ->call('cancelEdit')
            ->assertSet('new_title', '')
            ->assertHasNoErrors();
    }

    public function test_updating_without_opening_an_edit_changes_nothing(): void
    {
        $course = Course::factory()->create(['title' => 'دست‌نخورده']);

        // درخواستِ بدون دورهٔ باز نباید چیزی را تغییر دهد؛ بررسی نتیجهٔ
        // نهایی بهتر از بررسی نوع استثناست چون Livewire استثنا را می‌بلعد
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('updateCourse');

        $this->assertSame('دست‌نخورده', $course->fresh()->title);
    }

    public function test_editing_a_missing_course_leaves_the_form_closed(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', 99999)
            ->assertSet('showEditModal', false)
            ->assertSet('editingCourseId', null);
    }

    public function test_the_subject_can_be_changed(): void
    {
        $course = Course::factory()->create();
        $subject = Subject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->set('new_subject_id', (string) $subject->id)
            ->call('updateCourse')
            ->assertHasNoErrors();

        $this->assertSame($subject->id, $course->fresh()->subject_id);
    }

    public function test_the_status_is_not_editable_from_the_form(): void
    {
        // باز/بسته شدن دوره تصمیم آموزشی است، نه ویرایش متنی؛ از مسیر خودش
        $course = Course::factory()->closed()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('editCourse', $course->id)
            ->call('updateCourse');

        $this->assertSame('closed', $course->fresh()->status->value);
    }
}
