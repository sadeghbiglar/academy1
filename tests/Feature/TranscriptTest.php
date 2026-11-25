<?php

namespace Tests\Feature;

use App\Enums\GradeType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Grade;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Transcript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
||---------------------------------------------------------------------------
|| کارنامهٔ رسمی (شماره 37، قاعدهٔ R4)
||
|| سند رسمی است، پس سه چیز باید اثبات شود:
||   ۱. بدون layout پنل چاپ می‌شود — کارنامه‌ای که نیمه‌اش منو است سند نیست.
||   ۲. ریزنمرات، نمرهٔ نهایی و وضعیت قبولی روی کاغذ هستند و از همان
||      محاسبه‌ای می‌آیند که پنل استفاده می‌کند.
||   ۳. دانش‌آموزِ بدون نمره هم سند می‌گیرد، با وضعیت «فاقد سابقه» و بدون
||      خطا — نبودن نمره یعنی هنوز سنجیده نشده، نه اینکه صفر گرفته باشد.
||
|| نمرهٔ قبولی از تنظیمات می‌آید و مقدار خراب نباید همه را مردود کند.
*/

class TranscriptTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(Role $role): User
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user);

        return $user;
    }

    /** دانش‌آموزِ ثبت‌نام‌قطعی‌شده در یک دوره. */
    private function enrolled(): array
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        return [$student, $course];
    }

    // ── سند ────────────────────────────────────────────────

    public function test_the_transcript_is_generated_without_the_panel_chrome(): void
    {
        $this->actingAsRole(Role::Admin);
        [$student, $course] = $this->enrolled();

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 17);

        $response = $this->get(route('students.transcript.print', $student));

        $response->assertOk();
        // منو و سایهٔ پنل روی کاغذ نباید باشند
        $response->assertDontSee('drawer');

        $response->assertSee('کارنامهٔ رسمی')
            ->assertSee($student->first_name)
            ->assertSee($course->title)
            ->assertSee('پایان‌ترم')
            ->assertSee('ریزنمرات')
            ->assertSee('امضای و تأیید مدیر آموزشگاه');
    }

    public function test_a_passing_final_score_is_marked_as_accepted(): void
    {
        $this->actingAsRole(Role::Admin);
        [$student, $course] = $this->enrolled();

        Grade::record($student, $course, GradeType::Homework, 'تکلیف اول', 18);

        // وضعیت از روی کلاس سلول خوانده می‌شود، نه از متن: جمعِ پایین سند همیشه
        // «قبولی» و «مردود» را چاپ می‌کند، پس assertDontSee روی این کلمات دروغ می‌گوید
        $this->get(route('students.transcript.print', $student))
            ->assertOk()
            ->assertSee('class="center passed"', false)
            ->assertSee('قبولی');
    }

    public function test_a_failing_final_score_is_marked_as_failed(): void
    {
        $this->actingAsRole(Role::Deputy);
        [$student, $course] = $this->enrolled();

        // ۵ از ۲۰ یعنی ۲۵٪؛ میانگین وزنی همان ۵ را می‌دهد
        Grade::record($student, $course, GradeType::Midterm, 'میان‌ترم', 5);

        $this->get(route('students.transcript.print', $student))
            ->assertOk()
            ->assertSee('class="center failed"', false)
            ->assertSee('مردود');
    }

    public function test_a_student_without_any_grade_gets_a_document_marked_as_faqad_sabeghe(): void
    {
        $this->actingAsRole(Role::Admin);
        [$student] = $this->enrolled();

        $response = $this->get(route('students.transcript.print', $student));

        $response->assertOk();
        $response->assertSee('فاقد سابقه')
            ->assertSee($student->first_name);
        // سند باید امضا و شماره داشته باشد، حتی بدون نمره
        $response->assertSee('شماره سند')
            ->assertSee('امضای و تأیید مدیر آموزشگاه');
    }

    public function test_a_student_with_no_enrollment_at_all_still_gets_a_document(): void
    {
        $this->actingAsRole(Role::Admin);
        $student = Student::factory()->create();

        $this->get(route('students.transcript.print', $student))
            ->assertOk()
            ->assertSee('فاقد سابقه');
    }

    public function test_the_pass_score_comes_from_settings_and_a_broken_value_is_ignored(): void
    {
        $transcript = new Transcript;

        $this->assertSame(10.0, $transcript->passScore());

        Setting::put(Transcript::PASS_SCORE, '12.5');
        $this->assertSame(12.5, $transcript->passScore());

        // مقدار خارج از بازهٔ نمره نباید همه را مردود یا همه را قبول کند
        Setting::put(Transcript::PASS_SCORE, '900');
        $this->assertSame(Transcript::MAX_SCORE, $transcript->passScore());

        Setting::put(Transcript::PASS_SCORE, 'یک‌مقدارخراب');
        $this->assertSame(10.0, $transcript->passScore());
    }

    public function test_the_document_number_is_the_same_for_every_print_of_one_student(): void
    {
        $this->actingAsRole(Role::Admin);
        [$student] = $this->enrolled();

        $first = $this->get(route('students.transcript.print', $student))->getContent();
        $second = $this->get(route('students.transcript.print', $student))->getContent();

        $this->assertSame($first, $second);
    }

    // ── دسترسی ────────────────────────────────────────────

    public function test_finance_and_reception_cannot_open_the_transcript(): void
    {
        [$student] = $this->enrolled();

        foreach ([Role::Finance, Role::Reception] as $role) {
            $this->actingAsRole($role);

            $this->get(route('students.transcript.print', $student))->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        [$student] = $this->enrolled();

        $this->get(route('students.transcript.print', $student))
            ->assertRedirect(route('login'));
    }
}
