<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\WeightedAverage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| مدل ریزنمرات (شماره 49)
*/

class GradeTest extends TestCase
{
    use RefreshDatabase;

    private function enrolled(): Student
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        return $student;
    }

    private function freshCourse(): Course
    {
        return Course::factory()->create();
    }

    // ── ثبت ────────────────────────────────────────────────

    public function test_a_grade_is_recorded(): void
    {
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        $grade = Grade::record($student, $course, GradeType::Homework, 'تکلیف اول', 18);

        $this->assertModelExists($grade);
        $this->assertSame(GradeType::Homework, $grade->type);
        $this->assertEqualsWithDelta(90.0, $grade->percentage(), 0.01);
    }

    public function test_the_default_maximum_is_twenty(): void
    {
        $grade = Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Quiz, 'کوییز', 15);

        $this->assertEqualsWithDelta(20.0, (float) $grade->max_score, 0.01);
        $this->assertEqualsWithDelta(75.0, $grade->percentage(), 0.01);
    }

    public function test_the_same_assignment_twice_updates_instead_of_duplicating(): void
    {
        // دکمهٔ «ذخیره» دوباره زده می‌شود؛ دو ردیف یعنی میانگینِ دوباره‌شده
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::record($student, $course, GradeType::Homework, 'تکلیف اول', 18);
        Grade::record($student, $course, GradeType::Homework, 'تکلیف اول', 19);

        $this->assertSame(1, Grade::query()->count());
        $this->assertEqualsWithDelta(19.0, (float) Grade::query()->first()->score, 0.01);
    }

    public function test_the_database_rejects_a_duplicate_assignment(): void
    {
        // حتی اگر مدل را دور بزنیم
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'type' => GradeType::Quiz->value,
            'title' => 'کوییز',
            'score' => 15,
            'max_score' => 20,
        ]);

        $this->expectException(QueryException::class);

        Grade::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'type' => GradeType::Quiz->value,
            'title' => 'کوییز',
            'score' => 17,
            'max_score' => 20,
        ]);
    }

    public function test_two_quizzes_with_different_titles_are_not_duplicates(): void
    {
        // یک دانش‌آموز می‌تواند در یک ترم دو آزمون کوتاه داشته باشد
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::record($student, $course, GradeType::Quiz, 'کوییز اول', 15);
        Grade::record($student, $course, GradeType::Quiz, 'کوییز دوم', 18);

        $this->assertSame(2, Grade::query()->count());
    }

    public function test_the_same_title_in_another_course_is_not_a_duplicate(): void
    {
        $student = Student::factory()->create();

        Grade::record($student, $this->freshCourse(), GradeType::Homework, 'تکلیف اول', 18);
        Grade::record($student, $this->freshCourse(), GradeType::Homework, 'تکلیف اول', 18);

        $this->assertSame(2, Grade::query()->count());
    }

    // ── اعتبارسنجی نمره ───────────────────────────────────

    public function test_a_score_above_the_maximum_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Final, 'پایان‌ترم', 21);
    }

    public function test_a_score_equal_to_the_maximum_is_allowed(): void
    {
        $grade = Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Final, 'پایان‌ترم', 20);

        $this->assertEqualsWithDelta(100.0, $grade->percentage(), 0.01);
    }

    public function test_a_score_above_a_smaller_maximum_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        // نمرهٔ ۱۸ از ۱۵ غلط است، حتی اگر زیر ۲۰ باشد
        Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Quiz, 'کوییز', 18, maxScore: 15);
    }

    public function test_a_negative_score_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Quiz, 'کوییز', -1);
    }

    public function test_a_zero_maximum_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Quiz, 'کوییز', 0, maxScore: 0);
    }

    public function test_an_assignment_larger_than_twenty_points_is_allowed(): void
    {
        // آزمون ۲۵ نمره‌ای واقعی است؛ سقف ۲۰ فقط واحد نمایش است نه سقف نمره
        $grade = Grade::record(
            Student::factory()->create(),
            $this->freshCourse(),
            GradeType::Quiz,
            'کوییز',
            20,
            maxScore: 25,
        );

        $this->assertEqualsWithDelta(80.0, $grade->percentage(), 0.01);
    }

    public function test_the_percentage_is_computed_against_the_own_maximum(): void
    {
        $grade = Grade::record(
            Student::factory()->create(),
            $this->freshCourse(),
            GradeType::Homework,
            'تکلیف',
            8,
            maxScore: 10,
        );

        $this->assertEqualsWithDelta(80.0, $grade->percentage(), 0.01);
    }

    // ── نمایش ─────────────────────────────────────────────

    public function test_the_type_and_date_are_shown_in_persian(): void
    {
        $grade = Grade::record(
            Student::factory()->create(),
            $this->freshCourse(),
            GradeType::Midterm,
            'میان‌ترم',
            16,
            gradedAt: '2026-11-01',
        );

        $this->assertSame('میان‌ترم', $grade->typeLabel());
        $this->assertSame('1405/08/10', $grade->gradedAtJalali());
        $this->assertNull(Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Quiz, 'ک', 10)->gradedAtJalali());
    }

    public function test_the_score_is_shown_over_the_maximum(): void
    {
        $grade = Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Quiz, 'کوییز', 17.5);

        $this->assertSame(to_persian_digits('17.50').'/'.to_persian_digits('20.00'), $grade->scoreLabel());
    }

    public function test_the_recorder_is_kept(): void
    {
        $teacher = User::factory()->role(Role::Teacher)->create();

        $grade = Grade::record(
            Student::factory()->create(),
            $this->freshCourse(),
            GradeType::Homework,
            'تکلیف',
            18,
            by: $teacher,
        );

        $this->assertSame($teacher->id, $grade->user_id);
    }

    public function test_a_grade_survives_the_deletion_of_its_recorder(): void
    {
        // نمره سند آموزشگاه است، نه سند آن کارمند
        $teacher = User::factory()->role(Role::Teacher)->create();

        $grade = Grade::record(Student::factory()->create(), $this->freshCourse(), GradeType::Homework, 'تکلیف', 18, by: $teacher);

        $teacher->delete();

        $this->assertModelExists($grade->fresh());
        $this->assertNull($grade->fresh()->user_id);
    }

    public function test_every_grade_type_has_a_label_color_and_weight(): void
    {
        foreach (GradeType::cases() as $type) {
            $this->assertNotSame('', $type->label());
            $this->assertStringStartsWith('badge-', $type->color());
            $this->assertGreaterThan(0, $type->weight());
        }

        $this->assertSame('—', GradeType::labelFor(null));
    }

    // ── میانگین وزنی ───────────────────────────────────────

    public function test_the_final_score_is_out_of_twenty(): void
    {
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 16);

        $this->assertEqualsWithDelta(16.0, (new WeightedAverage)->finalScore($student, $course), 0.01);
    }

    public function test_a_student_with_no_grades_has_no_final_score(): void
    {
        $student = Student::factory()->create();

        $this->assertNull((new WeightedAverage)->finalScore($student, $this->freshCourse()));
    }

    public function test_the_final_exam_is_not_swamped_by_homework(): void
    {
        // میانگین ساده: ۲۰ تکلیف ۲۰نمره‌ای پایان‌ترم ۸ را کاملاً بی‌اثر می‌کرد
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        foreach (range(1, 20) as $index) {
            Grade::record($student, $course, GradeType::Homework, 'تکلیف '.$index, 20);
        }

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 8);

        $score = (new WeightedAverage)->finalScore($student, $course);

        // وزن‌ها: ۲۰ تکلیف با وزن ۳ = ۶۰، پایان‌ترم با وزن ۵ = ۵
        // درصدها: ۱۰۰ و ۴۰ → (100*3 + 40*5) / 8 = 62.5 → ۱۲٫۵ از ۲۰
        $this->assertEqualsWithDelta(12.5, $score, 0.01);
    }

    public function test_the_weights_are_applied_per_type_not_per_grade(): void
    {
        // وزن‌دادن به نمرهٔ خام یعنی یک تکلیف ۱۰نمره‌ای و پایان‌ترم ۲۰نمره‌ای
        // اسمی دو برابر وزن داشته باشند
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::record($student, $course, GradeType::Homework, 'تکلیف', 20, maxScore: 20);
        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 8, maxScore: 20);

        $this->assertEqualsWithDelta(12.5, (new WeightedAverage)->finalScore($student, $course), 0.01);
    }

    public function test_many_homework_grades_average_within_their_type(): void
    {
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::record($student, $course, GradeType::Homework, 'الف', 20, maxScore: 20);
        Grade::record($student, $course, GradeType::Homework, 'ب', 10, maxScore: 20);

        $this->assertEqualsWithDelta(15.0, (new WeightedAverage)->finalScore($student, $course), 0.01);
    }

    public function test_a_grade_from_a_different_maximum_is_normalised(): void
    {
        // ۸ از ۱۰ باید مثل ۱۶ از ۲۰ شمرده شود
        $student = Student::factory()->create();
        $course = $this->freshCourse();

        Grade::record($student, $course, GradeType::Quiz, 'کوییز الف', 8, maxScore: 10);
        Grade::record($student, $course, GradeType::Quiz, 'کوییز ب', 16, maxScore: 20);

        // هر دو ۸۰٪ هستند؛ میانگین وزنی یک نوع هم ۸۰٪ می‌شود، نه اینکه
        // نمرهٔ ۸ را با ۱۶ جمع کند و ۱۲ از ۲۰ بدهد
        $this->assertEqualsWithDelta(16.0, (new WeightedAverage)->finalScore($student, $course), 0.01);
    }

    // ── رتبه‌بندی ─────────────────────────────────────────

    public function test_students_are_ranked_by_final_score(): void
    {
        $course = Course::factory()->create();

        $top = Student::factory()->create(['first_name' => 'الف']);
        $middle = Student::factory()->create(['first_name' => 'ب']);
        $bottom = Student::factory()->create(['first_name' => 'ج']);

        foreach ([$top, $middle, $bottom] as $student) {
            CourseEnrollment::enroll($student, $course)->confirm();
        }

        Grade::record($top, $course, GradeType::Final, 'پایان‌ترم', 19);
        Grade::record($middle, $course, GradeType::Final, 'پایان‌ترم', 15);
        Grade::record($bottom, $course, GradeType::Final, 'پایان‌ترم', 10);

        $ranking = (new WeightedAverage)->rankStudents($course)
            ->keyBy(fn (array $row) => $row['student']->first_name);

        $this->assertSame(1, $ranking['الف']['rank']);
        $this->assertSame(2, $ranking['ب']['rank']);
        $this->assertSame(3, $ranking['ج']['rank']);
    }

    public function test_equal_scores_share_a_rank(): void
    {
        // رتبهٔ متفاوت برای نمرهٔ برابر یعنی کارنامهٔ دو نفر با هم نمی‌خواند
        $course = Course::factory()->create();

        foreach (range(1, 3) as $ignored) {
            $student = Student::factory()->create();
            CourseEnrollment::enroll($student, $course)->confirm();
            Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 17);
        }

        $ranks = (new WeightedAverage)->rankStudents($course)->pluck('rank')->all();

        $this->assertSame([1, 1, 1], $ranks);
    }

    public function test_a_tie_skips_the_next_rank(): void
    {
        // دو نفر اول مساوی: نفر سوم رتبهٔ ۳ می‌گیرد نه ۲
        $course = Course::factory()->create();

        $scores = [17, 17, 12];

        foreach ($scores as $score) {
            $student = Student::factory()->create();
            CourseEnrollment::enroll($student, $course)->confirm();
            Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', $score);
        }

        $ranks = (new WeightedAverage)->rankStudents($course)->pluck('rank')->sort()->values()->all();

        $this->assertSame([1, 1, 3], $ranks);
    }

    public function test_a_student_without_grades_gets_no_rank(): void
    {
        // رتبهٔ انتهای فهرست یعنی «بدترین» و این برداشت نادرستی است
        $course = Course::factory()->create();

        $withGrades = Student::factory()->create();
        $without = Student::factory()->create();

        foreach ([$withGrades, $without] as $student) {
            CourseEnrollment::enroll($student, $course)->confirm();
        }

        Grade::record($withGrades, $course, GradeType::Final, 'پایان‌ترم', 12);

        $ranking = (new WeightedAverage)->rankStudents($course)
            ->keyBy(fn (array $row) => $row['student']->id);

        $this->assertSame(1, $ranking[$withGrades->id]['rank']);
        $this->assertNull($ranking[$without->id]['rank']);
    }

    public function test_only_confirmed_enrolments_are_ranked(): void
    {
        // رزرو تأییدنشده در رتبه‌بندی جایی ندارد
        $course = Course::factory()->create();

        $confirmed = Student::factory()->create();
        $waiting = Student::factory()->create();

        CourseEnrollment::enroll($confirmed, $course)->confirm();
        CourseEnrollment::enroll($waiting, $course);

        Grade::record($confirmed, $course, GradeType::Final, 'پایان‌ترم', 12);

        $ranking = (new WeightedAverage)->rankStudents($course);

        $this->assertCount(1, $ranking);
        $this->assertSame(EnrollmentStatus::Confirmed, $confirmed->enrollments()->first()->status);
    }

    public function test_the_ranking_is_ordered_from_highest_to_lowest(): void
    {
        $course = Course::factory()->create();

        foreach ([10, 19, 14] as $score) {
            $student = Student::factory()->create();
            CourseEnrollment::enroll($student, $course)->confirm();
            Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', $score);
        }

        $scores = (new WeightedAverage)->rankStudents($course)->pluck('score')->all();

        $this->assertSame([19.0, 14.0, 10.0], $scores);
    }
}
