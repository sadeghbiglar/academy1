<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\GradeType;
use App\Enums\Role;
use App\Enums\WeekDay;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Grade;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\CourseTranscript;
use App\Services\Transcript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
||---------------------------------------------------------------------------
|| کارنامهٔ نهایی دوره (شماره 51)
||
|| این سند «یک دوره تمام شد، چه بلایی سر ثبت‌نام‌ها آمد» را جواب می‌دهد،
|| پس سطرش یک دانش‌آموز است نه یک دوره، و سه چیز باید اثبات شود:
||   ۱. ریزنمرات به تفکیک نوع خوانا هستند و تاریخشان شمسی است.
||   ۲. نمرهٔ نهایی همان میانگین وزنی‌ای است که پنل می‌سازد، با نمرهٔ قبولی
||      از تنظیمات — نه با یک ثابتِ داخل کد.
||   ۳. وضعیت سه حالت واقعی دارد: قبولی، مردود و مشروط.
||
|| «دو نمره زیر حد → failed» تستِ الزامی issue است: اگر قاعدهٔ مشروط طوری
|| نوشته شود که هر نمرهٔ زیر حد را مشروط کند، همین تست می‌ایستد.
*/

class CourseTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(Role $role): User
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user);

        return $user;
    }

    /** دورهٔ با ۱۰ جلسهٔ ۹۰دقیقه‌ای — ۱۵ ساعت، برای محاسبهٔ ساعت‌ها. */
    private function course(): Course
    {
        return Course::factory()->create([
            'session_count' => 10,
            'session_duration' => 90,
        ]);
    }

    private function enrolled(Course $course): Student
    {
        $student = Student::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        return $student;
    }

    /**
     * جلسه‌های ۹۰ دقیقه‌ای روی روزهای مختلف؛ دو جلسه در یک روز و یک هفته
     * تداخل زمانی دارند و ثبتشان ValidationException می‌دهد.
     *
     * @param  array<int, AttendanceStatus>  $statuses
     * @return array<int, ClassSession>
     */
    private function sessions(Course $course, Student $student, array $statuses): array
    {
        $days = [WeekDay::Saturday, WeekDay::Sunday, WeekDay::Monday];

        $sessions = [];

        foreach ($statuses as $index => $status) {
            $session = ClassSession::factory()
                ->at($days[$index]->value, 9 * 60, 10 * 60 + 30, 1)
                ->create(['course_id' => $course->id]);

            Attendance::recordFor($session, [[
                'student_id' => $student->id,
                'status' => $status->value,
            ]]);

            $sessions[] = $session;
        }

        return $sessions;
    }

    // ── سند ────────────────────────────────────────────────

    public function test_the_document_is_printed_without_the_panel_chrome(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();
        $student = $this->enrolled($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 17);

        $response = $this->get(route('courses.transcript.print', $course));

        $response->assertOk();
        $response->assertDontSee('drawer');
        $response->assertSee('کارنامهٔ نهایی دوره')
            ->assertSee($course->title)
            ->assertSee($course->teacher->full_name)
            ->assertSee('ریزنمرات')
            ->assertSee('ساعت حضور')
            ->assertSee('امضای استاد دوره');
    }

    public function test_two_grades_below_the_pass_score_mark_the_student_failed(): void
    {
        $this->actingAsRole(Role::Deputy);
        $course = $this->course();
        $student = $this->enrolled($course);

        // هر دو زیر نمرهٔ قبولی ۱۰: میانگین وزنی هم زیر ۱۰ در می‌آید، پس
        // نتیجه باید «مردود» باشد نه «مشروط» — این همان قاعدهٔ issue است
        Grade::record($student, $course, GradeType::Homework, 'تکلیف اول', 6);
        Grade::record($student, $course, GradeType::Quiz, 'آزمون اول', 8);

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('class="center failed"', false)
            ->assertSee('مردود');
    }

    public function test_a_passing_score_with_short_attendance_is_conditional(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();
        $student = $this->enrolled($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 18);
        // یک جلسه حاضر، یک جلسه غایب → ۵۰٪ حضور، زیر آستانهٔ ۸۰٪
        $this->sessions($course, $student, [AttendanceStatus::Present, AttendanceStatus::Absent]);

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('class="center conditional"', false)
            ->assertSee('قبولی مشروط');
    }

    public function test_a_passing_score_with_enough_attendance_is_passed(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();
        $student = $this->enrolled($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 18);
        $this->sessions($course, $student, [AttendanceStatus::Present, AttendanceStatus::Present]);

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('class="center passed"', false)
            ->assertDontSee('class="center conditional"', false);
    }

    public function test_a_student_without_grades_is_pending_not_failed(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();
        $this->enrolled($course);

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('class="center pending"', false)
            ->assertSee('در انتظار نمره')
            ->assertDontSee('class="center failed"', false);
    }

    // ── ساعت حضور ─────────────────────────────────────────

    public function test_attended_hours_count_only_the_sessions_the_student_sat_in(): void
    {
        $course = $this->course();
        $student = $this->enrolled($course);

        // سه جلسهٔ ۹۰دقیقه‌ای: حاضر، غایب و موجه. فقط حاضر ساعت می‌سازد
        $this->sessions($course, $student, [
            AttendanceStatus::Present,
            AttendanceStatus::Absent,
            AttendanceStatus::Excused,
        ]);

        $this->assertSame(1.5, Attendance::attendedHoursFor($student, $course));

        $doc = app(CourseTranscript::class)->build($course);

        $this->assertSame(1.5, $doc['rows'][0]['attended_hours']);
        // موجه از مخرج درصد بیرون است: یک حاضر از دو شمارشی، یعنی ۵۰٪
        $this->assertSame(50.0, $doc['rows'][0]['attendance']);
    }

    // ── نمرهٔ قبولی و ریزنمرات ────────────────────────────

    public function test_the_pass_score_comes_from_settings_not_from_a_constant(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();
        $student = $this->enrolled($course);

        Setting::put(Transcript::PASS_SCORE, '12.5');

        // ۱۱ با نمرهٔ قبولیِ پیش‌فرض قبول بود، با تنظیمات آموزشگاه مردود است
        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 11);

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('۱۲٫۵', false)
            ->assertSee('class="center failed"', false);
    }

    public function test_the_breakdown_groups_grades_by_type_and_shows_shamsi_dates(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();
        $student = $this->enrolled($course);

        Grade::record($student, $course, GradeType::Quiz, 'آزمون اول', 15, null, null, '2026-01-15');
        Grade::record($student, $course, GradeType::Homework, 'تکلیف اول', 18, null, null, '2026-01-10');

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('ریزنمرات')
            ->assertSee('تکلیف اول')
            ->assertSee('آزمون اول')
            ->assertSee('آزمون کوتاه')
            // تاریخِ روی سند شمسی است؛ میلادیِ خام نباید به کاغذ برسد (R1)
            ->assertSee(jalali_date('2026-01-15'))
            ->assertDontSee('2026-01-15');
    }

    // ── سند بودن ──────────────────────────────────────────

    public function test_the_document_number_is_the_same_for_every_print(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();

        $first = $this->get(route('courses.transcript.print', $course))->getContent();
        $second = $this->get(route('courses.transcript.print', $course))->getContent();

        $this->assertSame(
            $this->documentOnly($first),
            $this->documentOnly($second),
        );
    }

    /**
     * بلوک‌هایی که Livewire به بعضی پاسخ‌ها تزریق می‌کند جزء سند نیستند.
     *
     * تزریقِ آن‌ها به وضعیتِ باقی‌ماندهٔ از تست‌های قبلی بستگی دارد: یک پاسخ
     * «Livewire Styles/Scripts» را دارد و پاسخ بعدی ندارد. compareی کلیِ HTML
     * بدون این، دو چاپِ یک سندِ کاملاً یکسان را غیریکسان اعلام می‌کند — و
     * چیزی که باید یکی باشد خودِ کارنامه است، نه اسکریپتِ فریم‌ورک.
     */
    private function documentOnly(string $html): string
    {
        $html = preg_replace('/<!-- Livewire Styles -->.*?<\/head>/s', '</head>', $html);
        $html = preg_replace('/<!-- Livewire Scripts -->.*?<\/body>/s', '</body>', $html);

        return $html;
    }

    public function test_a_course_without_any_enrollment_still_gets_a_document(): void
    {
        $this->actingAsRole(Role::Admin);
        $course = $this->course();

        $this->get(route('courses.transcript.print', $course))
            ->assertOk()
            ->assertSee('فاقد سابقه')
            ->assertSee($course->title)
            ->assertSee('شماره سند');
    }

    // ── دسترسی ────────────────────────────────────────────

    public function test_finance_and_reception_cannot_open_the_course_transcript(): void
    {
        $course = $this->course();

        foreach ([Role::Finance, Role::Reception] as $role) {
            $this->actingAsRole($role);

            $this->get(route('courses.transcript.print', $course))->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $course = $this->course();

        $this->get(route('courses.transcript.print', $course))
            ->assertRedirect(route('login'));
    }

    public function test_the_link_is_offered_only_where_grades_are_allowed(): void
    {
        $course = $this->course();
        $link = route('courses.transcript.print', $course);

        // مدیر و معاون و دبیر نمره می‌بینند، پس سند را هم می‌گیرند
        foreach ([Role::Admin, Role::Deputy, Role::Teacher] as $role) {
            $this->actingAsRole($role);

            Livewire::test('pages::courses.index')
                ->call('viewCourse', $course->id)
                ->assertSee($link);
        }

        // امور مالی و پذیرش نمره نمی‌بینند؛ لینکِ مرده در مودال نباید باشد
        foreach ([Role::Finance, Role::Reception] as $role) {
            $this->actingAsRole($role);

            Livewire::test('pages::courses.index')
                ->call('viewCourse', $course->id)
                ->assertDontSee($link);
        }
    }
}
