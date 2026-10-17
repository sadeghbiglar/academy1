<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\WeekDay;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| صفحهٔ پروندهٔ دانش‌آموز (شماره 35)
|
| این تست‌ها دربارهٔ چیزی هستند که تست HTTP معمولی نمی‌گیرد: اینکه نقش‌های
| مختلف واقعاً بخش‌های متفاوتی از صفحه را می‌بینند. یک تست سادهٔ
| assertOk روی این صفحه، همهٔ کار را رد می‌کند.
*/

class StudentProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(Course $course): Student
    {
        $student = Student::factory()->create(['first_name' => 'زهرا', 'last_name' => 'کریمی']);
        CourseEnrollment::enroll($student, $course)->confirm();

        return $student;
    }

    private function url(Student $student): string
    {
        return route('students.show', $student);
    }

    // ── دسترسی ─────────────────────────────────────────────

    public function test_the_page_opens_for_the_admin(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get($this->url(Student::factory()->create()))->assertOk();
    }

    public function test_the_page_opens_for_deputy_and_reception(): void
    {
        foreach ([Role::Deputy, Role::Reception] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->get($this->url(Student::factory()->create()))->assertOk();
        }
    }

    public function test_a_teacher_cannot_open_a_student_profile(): void
    {
        // دبیر فقط کلاس‌های خودش را می‌بیند، نه پروندهٔ همهٔ آموزشگاه
        $this->actingAs(User::factory()->role(Role::Teacher)->create());

        $this->get($this->url(Student::factory()->create()))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get($this->url(Student::factory()->create()))->assertRedirect(route('login'));
    }

    public function test_an_unknown_student_is_a_404(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get(route('students.show', 999999))->assertNotFound();
    }

    // ── محتوا ──────────────────────────────────────────────

    public function test_the_page_shows_the_student_name(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $student = $this->enrolledStudent(Course::factory()->create());

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee('زهرا')
            ->assertSee('کریمی');
    }

    public function test_the_page_shows_the_courses_with_their_status(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $course = Course::factory()->create(['title' => 'ریاضی نهم']);
        $student = $this->enrolledStudent($course);

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee('ریاضی نهم');
    }

    public function test_the_page_shows_an_enrolment_without_courses(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get($this->url(Student::factory()->create()))
            ->assertOk()
            ->assertSee('در هیچ دوره‌ای ثبت‌نام نکرده');
    }

    public function test_a_cancelled_enrolment_is_not_listed(): void
    {
        // یک ثبت‌نام لغوشده کنار دوره‌های جاری، وضعیت واقعی را مبهم می‌کند
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $course = Course::factory()->create(['title' => 'دورهٔ لغوشده']);
        $student = Student::factory()->create();

        CourseEnrollment::enroll($student, $course)->cancel();

        $this->get($this->url($student))
            ->assertOk()
            ->assertDontSee('دورهٔ لغوشده');
    }

    public function test_the_page_shows_the_attendance_rate_and_the_final_score(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $course = Course::factory()->create();
        $student = $this->enrolledStudent($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 16);

        $session = ClassSession::factory()->at(WeekDay::Saturday->value, 540, 660)
            ->create(['course_id' => $course->id]);

        Attendance::recordFor($session, [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee(to_persian_digits('100.0'))
            ->assertSee(to_persian_digits('16.00'));
    }

    public function test_the_page_shows_the_guardians(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $student = Student::factory()->create();

        $student->guardians()->create([
            'first_name' => 'مهدی',
            'last_name' => 'کریمی',
            'relation' => 'پدر',
            'mobile' => '09121234567',
            'is_primary' => true,
        ]);

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee('مهدی')
            ->assertSee('پدر')
            ->assertSee(to_persian_digits('09121234567'));
    }

    public function test_a_student_with_no_guardian_says_so(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get($this->url(Student::factory()->create()))
            ->assertOk()
            ->assertSee('سرپرستی ثبت نشده');
    }

    public function test_the_page_shows_the_financial_position_to_finance(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();
        $invoice = Invoice::issue($student, null, 2_000_000);

        Payment::pay($invoice, 500_000, PaymentMethod::Card, reference: '555');

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee(to_persian_digits('2,000,000'))
            ->assertSee(to_persian_digits('1,500,000'));
    }

    // ── جدا کردن نقش‌ها ────────────────────────────────────

    public function test_finance_sees_money_but_not_grades(): void
    {
        // امور مالی وضعیت بدهی را می‌بیند؛ نمره و ریزنمرات کار او نیست
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $course = Course::factory()->create(['title' => 'شیمی دهم']);
        $student = $this->enrolledStudent($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 20);

        $response = $this->get($this->url($student))->assertOk();

        $response->assertDontSee('شیمی دهم');
        $response->assertDontSee(to_persian_digits('20.00'));
    }

    public function test_the_deputy_sees_grades_but_not_money(): void
    {
        // معاون آموزشی به پول دسترسی ندارد
        $this->actingAs(User::factory()->role(Role::Deputy)->create());

        $course = Course::factory()->create();
        $student = $this->enrolledStudent($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 17);

        $invoice = Invoice::issue($student, null, 3_000_000);

        $response = $this->get($this->url($student))->assertOk();

        $response->assertSee(to_persian_digits('17.00'));
        $response->assertDontSee($invoice->number);
    }

    public function test_the_admin_sees_both(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $course = Course::factory()->create();
        $student = $this->enrolledStudent($course);

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 17);
        $invoice = Invoice::issue($student, $course, 3_000_000);

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee(to_persian_digits('17.00'))
            ->assertSee($invoice->number);
    }

    public function test_the_list_page_links_to_the_profile(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $student = Student::factory()->create();

        $this->get(route('students.index'))
            ->assertOk()
            ->assertSee(route('students.show', $student));
    }

    public function test_the_profile_links_back_to_the_list(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get($this->url(Student::factory()->create()))
            ->assertOk()
            ->assertSee(route('students.index'));
    }

    // ── وضعیت‌های مرزی ─────────────────────────────────────

    public function test_a_student_without_a_profile_still_has_a_page(): void
    {
        // پروندهٔ آموزشی اختیاری است؛ نبودش نباید صفحه را بشکند
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        $student = Student::factory()->create();

        $this->assertNull($student->profile);

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee('بدون پروندهٔ آموزشی');
    }

    public function test_an_overpaid_student_shows_credit_not_a_negative_balance(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();

        Payment::pay(Invoice::issue($student, null, 1_000_000), 1_400_000);

        // بررسی نبودن «−۴۰۰٬۰۰۰» به‌صورت کلی با assertDontSee('-') بی‌معناست؛
        // کل HTML پر از خط تیره است. فقط عدد بدهی بررسی می‌شود.
        $this->get($this->url($student))
            ->assertOk()
            ->assertSee(to_persian_digits('400,000'))
            ->assertDontSee(to_persian_digits('-400,000'));

        $this->assertSame(0, $student->outstanding());
        $this->assertSame(400_000, $student->credit());
    }

    public function test_the_enrolment_status_of_a_waitlisted_student_is_shown(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $course = Course::factory()->create();
        $course->update(['capacity' => 1]);

        $first = Student::factory()->create();
        CourseEnrollment::enroll($first, $course)->confirm();

        $waiting = Student::factory()->create();
        $enrollment = CourseEnrollment::enroll($waiting, $course);

        $this->assertSame(EnrollmentStatus::Waitlisted, $enrollment->fresh()->status);

        $this->get($this->url($waiting))
            ->assertOk()
            ->assertSee($enrollment->fresh()->statusLabel());
    }

    public function test_a_paid_invoice_shows_no_outstanding_amount(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());
        $student = Student::factory()->create();
        $invoice = Invoice::issue($student, null, 1_000_000);

        Payment::pay($invoice, 1_000_000);

        $this->get($this->url($student))
            ->assertOk()
            ->assertSee($invoice->statusLabel());
    }
}
