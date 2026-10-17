<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| فرم ایجاد دوره (شماره 11)
*/

class CourseCreateFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'new_title' => 'دورهٔ جمع‌بندی ریاضی',
            'new_code' => 'MATH-1',
            'new_subject_id' => (string) Subject::factory()->create()->id,
            'new_teacher_id' => '',
            'new_capacity' => '15',
            'new_session_count' => '12',
            'new_session_duration' => '90',
            'new_base_fee' => '2000000',
            'new_starts_at' => '',
        ], $overrides);
    }

    public function test_a_course_is_created_through_the_form(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload())
            ->call('createCourse')
            ->assertHasNoErrors();

        $course = Course::query()->first();

        $this->assertNotNull($course);
        $this->assertSame('دورهٔ جمع‌بندی ریاضی', $course->title);
        $this->assertSame('MATH-1', $course->code);
        $this->assertSame(15, $course->capacity);
        $this->assertSame(CourseStatus::Open, $course->status);
    }

    public function test_the_title_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_title' => '']))
            ->call('createCourse')
            ->assertHasErrors('new_title');

        $this->assertSame(0, Course::query()->count());
    }

    public function test_a_duplicate_code_is_rejected(): void
    {
        Course::factory()->create(['code' => 'MATH-1']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload())
            ->call('createCourse')
            ->assertHasErrors('new_code');

        $this->assertSame(1, Course::query()->count());
    }

    public function test_a_blank_code_is_allowed_and_stored_as_null(): void
    {
        // رشتهٔ خالی و NULL فرق دارند؛ رشتهٔ خالی در جست‌وجوی کد چند دورهٔ
        // متفاوت را یکی نشان می‌دهد
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_code' => '']))
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertNull(Course::query()->first()->code);
    }

    public function test_capacity_may_be_zero_for_unlimited(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_capacity' => '0']))
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertSame(0, Course::query()->first()->capacity);
    }

    public function test_a_negative_capacity_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_capacity' => '-5']))
            ->call('createCourse')
            ->assertHasErrors('new_capacity');
    }

    public function test_a_negative_fee_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_base_fee' => '-1']))
            ->call('createCourse')
            ->assertHasErrors('new_base_fee');
    }

    public function test_a_zero_fee_is_allowed(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_base_fee' => '0']))
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertSame(0, Course::query()->first()->base_fee);
    }

    public function test_a_zero_session_duration_is_rejected(): void
    {
        // جلسهٔ صفر دقیقه یعنی این دوره هیچ زمانی ندارد
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_session_duration' => '0']))
            ->call('createCourse')
            ->assertHasErrors('new_session_duration');
    }

    public function test_the_subject_must_exist(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_subject_id' => '99999']))
            ->call('createCourse')
            ->assertHasErrors('new_subject_id');
    }

    public function test_a_course_can_be_created_without_a_teacher(): void
    {
        // استاد همیشه هنگام ثبت‌نام نیست؛ دورهٔ بدون استاد باید ممکن باشد
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload())
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertNull(Course::query()->first()->teacher_id);
    }

    public function test_a_teacher_is_attached_when_given(): void
    {
        $teacher = Teacher::factory()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_teacher_id' => (string) $teacher->id]))
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertSame($teacher->id, Course::query()->first()->teacher_id);
    }

    public function test_the_start_date_is_stored_gregorian(): void
    {
        // مقدار JalaliDatePicker همیشه میلادی ISO است
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_starts_at' => '2026-11-01']))
            ->call('createCourse')
            ->assertHasNoErrors();

        $course = Course::query()->first();

        $this->assertSame('2026-11-01', $course->fresh()->starts_at->format('Y-m-d'));
        $this->assertSame('1405/08/10', $course->startsAtJalali());
    }

    public function test_a_blank_start_date_is_allowed(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload())
            ->call('createCourse')
            ->assertHasNoErrors();

        $this->assertNull(Course::query()->first()->starts_at);
    }

    public function test_a_malformed_start_date_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_starts_at' => 'فردا صبح']))
            ->call('createCourse')
            ->assertHasErrors('new_starts_at');
    }

    public function test_the_form_resets_after_a_successful_create(): void
    {
        // باقی ماندن دادهٔ دورهٔ قبلی در فرم یعنی ثبت‌نام اشتباه بعدی
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload())
            ->call('createCourse')
            ->assertSet('new_title', '')
            ->assertSet('new_code', '')
            ->assertSet('showCreateModal', false);
    }

    public function test_only_roles_allowed_to_create_can_reach_the_page(): void
    {
        // پذیرش دوره ثبت می‌کند و دبیر هم به کلاس خودش دوره اضافه می‌کند
        $this->actingAs(User::factory()->role(Role::Reception)->create())->get('/courses')->assertOk();
        $this->actingAs(User::factory()->role(Role::Teacher)->create())->get('/courses')->assertOk();
    }

    public function test_the_new_course_appears_in_the_list(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set($this->validPayload(['new_title' => 'دورهٔ تازه']))
            ->call('createCourse')
            ->assertSee('دورهٔ تازه');
    }
}
