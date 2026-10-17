<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| صفحهٔ فهرست دوره‌ها (شماره 10)
*/

class CourseIndexPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_page_renders_and_lists_courses(): void
    {
        Course::factory()->create(['title' => 'دورهٔ آزمایشی الف']);

        $this->actingAs($this->admin())->get('/courses')->assertOk();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee('دورهٔ آزمایشی الف');
    }

    public function test_the_page_is_gated_by_role(): void
    {
        // امور مالی دوره را می‌بیند برای فاکتور، ولی فهرست آموزشی کار او نیست
        $this->actingAs(User::factory()->role(Role::Finance)->create())
            ->get('/courses')
            ->assertForbidden();
    }

    public function test_a_teacher_reaches_the_page(): void
    {
        $this->actingAs(User::factory()->role(Role::Teacher)->create())
            ->get('/courses')
            ->assertOk();
    }

    public function test_search_matches_the_title(): void
    {
        Course::factory()->create(['title' => 'دورهٔ ریاضی شب امتحان']);

        Course::factory()->create([
            'title' => 'دورهٔ زیست',
            'subject_id' => Subject::factory()->create(['name' => 'زیست']),
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set('search', 'شب امتحان')
            ->assertSee('دورهٔ ریاضی شب امتحان')
            ->assertDontSee('دورهٔ زیست');
    }

    public function test_search_also_matches_the_subject_name(): void
    {
        // عنوانی که کاربر می‌زند در ردیف دوره نیست، در رکورد درس است
        $subject = Subject::factory()->create(['name' => 'شیمی پیشرفته']);

        Course::factory()->create(['title' => 'دورهٔ الف', 'subject_id' => $subject->id]);

        // صریح: factory وقتی درس موجود باشد به یکی تصادفی وصل می‌کند، پا�� «ب» هم
        // همان درس شیمی می‌گرفت و تست هیچ‌چیز را ثابت نمی‌کرد
        Course::factory()->create([
            'title' => 'دورهٔ ب',
            'subject_id' => Subject::factory()->create(['name' => 'تاریخ']),
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set('search', 'شیمی')
            ->assertSee('دورهٔ الف')
            ->assertDontSee('دورهٔ ب');
    }

    public function test_search_matches_the_teacher_name(): void
    {
        $teacher = Teacher::factory()->create([
            'first_name' => 'مریم',
            'last_name' => 'کیانی',
        ]);

        Course::factory()->create([
            'title' => 'دورهٔ الف',
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['name' => 'تاریخ']),
        ]);

        Course::factory()->create([
            'title' => 'دورهٔ ب',
            'teacher_id' => Teacher::factory()->create(),
            'subject_id' => Subject::factory()->create(['name' => 'جغرافیا']),
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set('search', 'کیانی')
            ->assertSee('دورهٔ الف')
            ->assertDontSee('دورهٔ ب');
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        Course::factory()->count(2)->create();
        Course::factory()->closed()->create(['title' => 'دورهٔ بسته']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set('statusFilter', CourseStatus::Closed->value)
            ->assertSee('دورهٔ بسته')
            ->assertSee('بسته');
    }

    public function test_clearing_the_filters_restores_the_list(): void
    {
        Course::factory()->count(3)->create();
        Course::factory()->closed()->create(['title' => 'دورهٔ بسته']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->set('statusFilter', CourseStatus::Closed->value)
            ->set('search', 'بسته')
            ->call('clearFilters')
            ->assertSet('statusFilter', '')
            ->assertSet('search', '');
    }

    public function test_the_page_returns_to_page_one_when_the_filter_changes(): void
    {
        // وگرنه کاربر روی صفحهٔ ۵ می‌ماند در حالی که نتیجه فقط یک صفحه است
        Course::factory()->count(40)->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->call('setPage', 3)
            ->assertSet('paginators.page', 3)
            ->set('search', 'چیزی که نیست')
            ->assertSet('paginators.page', 1);
    }

    public function test_the_ordering_is_deterministic(): void
    {
        // بدون ترتیب قطعی، صفحه‌بندی ردیف تکراری یا جاافتاده نشان می‌دهد
        Course::factory()->count(20)->create();

        $ids = function () {
            $component = Livewire::actingAs($this->admin())->test('pages::courses.index');

            return $component->instance()->courses->pluck('id')->all();
        };

        $this->assertSame($ids(), $ids());
    }

    public function test_enrolled_capacity_is_shown_out_of_the_total(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();
        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee(to_persian_digits(2).' / '.to_persian_digits(10));
    }

    public function test_waitlisted_and_pending_students_do_not_count_towards_capacity(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        CourseEnrollment::enroll(Student::factory()->create(), $course);
        CourseEnrollment::enroll(Student::factory()->create(), $course);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee(to_persian_digits(0).' / '.to_persian_digits(10));
    }

    public function test_an_unlimited_course_shows_no_numbers(): void
    {
        // ظرفیت صفر یعنی بی‌نهایت؛ نشان دادن «۰ / ۰» گمراه‌کننده است
        Course::factory()->create(['capacity' => 0]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee('نامحدود');
    }

    public function test_a_free_course_is_marked_rаیgan(): void
    {
        Course::factory()->create(['base_fee' => 0]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee('رایگان');
    }

    public function test_the_fee_is_shown_in_toman_not_raw_rial(): void
    {
        // ۲۰٬۰۰۰٬۰۰۰ ریال = ۲٬۰۰۰٬۰۰۰ تومان. نمایش عدد خام یعنی ده برابر اشتباه
        Course::factory()->create(['base_fee' => 20_000_000]);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee(to_persian_digits('2,000,000').' تومان');
    }

    public function test_the_start_date_is_shown_in_jalali(): void
    {
        Course::factory()->create(['starts_at' => '2026-11-01']);

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee('1405/08/10');
    }

    public function test_a_course_without_a_teacher_says_so(): void
    {
        Course::factory()->withoutTeacher()->create();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee('تعیین نشده');
    }

    public function test_an_empty_list_says_so(): void
    {
        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee('هنوز هیچ دوره‌ای ثبت شده است');
    }

    public function test_the_capacity_column_is_not_silently_zero(): void
    {
        // هشدار: mary در @scope محیط تازه‌ای می‌سازد و دادهٔ والد را به ارث
        // نمی‌برد. اولین نسخهٔ این صفحه $counts را از render() می‌فرستاد و همهٔ
        // دوره‌ها بی‌صدا «۰ / ظرفیت» نشان می‌دادند — بدون خطا، بدون استثنا.
        $course = Course::factory()->create(['capacity' => 4]);

        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        Livewire::actingAs($this->admin())
            ->test('pages::courses.index')
            ->assertSee(to_persian_digits(1).' / '.to_persian_digits(4));
    }

    public function test_the_menu_links_to_the_courses_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('courses.index').'"', escape: false);
    }
}
