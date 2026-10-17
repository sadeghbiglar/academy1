<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Document;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| پروندهٔ کامل دانش‌آموز (شماره 34)
*/

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_can_be_created_with_a_valid_national_code(): void
    {
        $student = Student::create([
            'first_name' => 'زهرا',
            'last_name' => 'کریمی',
            'national_code' => '0084575948',
            'mobile' => '09121234567',
            'gender' => 'female',
        ]);

        $this->assertNotNull($student->id);
        $this->assertSame('زهرا', $student->first_name);
        $this->assertSame('0084575948', $student->national_code);
    }

    public function test_the_national_code_column_rejects_a_duplicate(): void
    {
        Student::create([
            'first_name' => 'زهرا',
            'last_name' => 'کریمی',
            'national_code' => '0084575948',
            'mobile' => '09121234567',
        ]);

        $this->expectException(QueryException::class);

        // همان کد ملی برای دو نفر: خطا باید از لایهٔ پایگاه داده بیاید، نه از
        // یک بررسی در UI که یک بار فراموش شود
        Student::create([
            'first_name' => 'مریم',
            'last_name' => 'احمدی',
            'national_code' => '0084575948',
            'mobile' => '09121234568',
        ]);
    }

    public function test_the_national_code_may_be_null(): void
    {
        $student = Student::create([
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'mobile' => '09121234569',
        ]);

        $this->assertNull($student->national_code);
    }

    public function test_a_student_has_one_optional_profile(): void
    {
        $student = Student::factory()->create();

        $this->assertNull($student->profile);

        $profile = StudentProfile::factory()->create(['student_id' => $student->id]);

        $this->assertTrue($profile->isActive() === false);
        $this->assertSame('در حال ثبت‌نام', $student->fresh()->statusLabel());
    }

    public function test_a_student_cannot_have_two_profiles(): void
    {
        $student = Student::factory()->create();
        StudentProfile::factory()->create(['student_id' => $student->id]);

        $this->expectException(QueryException::class);

        StudentProfile::create(['student_id' => $student->id, 'student_code' => '1404-0002']);
    }

    public function test_the_student_code_is_unique_when_given(): void
    {
        StudentProfile::factory()->create(['student_code' => '1404-0001']);

        $this->expectException(QueryException::class);

        StudentProfile::factory()->create(['student_code' => '1404-0001']);
    }

    public function test_two_students_may_both_have_a_null_code(): void
    {
        // ثبت سریع پذیرش نباید به کد نیاز داشته باشد، و یکتایی nullable
        // در sqlite/mysql باید چند NULL را بپذیرد
        $a = StudentProfile::factory()->create(['student_code' => null]);
        $b = StudentProfile::factory()->create(['student_code' => null]);

        $this->assertNull($a->student_code);
        $this->assertNull($b->student_code);
    }

    public function test_a_student_can_have_several_guardians_but_only_one_primary(): void
    {
        $student = Student::factory()->create();

        Guardian::factory()->primary()->create(['student_id' => $student->id]);
        Guardian::factory()->create(['student_id' => $student->id, 'relation' => 'mother']);
        Guardian::factory()->create(['student_id' => $student->id, 'relation' => 'uncle']);

        $this->assertCount(3, $student->guardians);
        $this->assertSame(1, $student->guardians()->where('is_primary', true)->count());
    }

    public function test_a_second_primary_guardian_is_rejected(): void
    {
        $student = Student::factory()->create();
        Guardian::factory()->primary()->create(['student_id' => $student->id]);

        $this->expectException(ValidationException::class);

        Guardian::factory()->primary()->create(['student_id' => $student->id]);
    }

    public function test_the_primary_guardian_can_still_be_edited(): void
    {
        // بررسی ساخته‌شده در saving نباید رکورد در حال ویرایش را با خودش
        // تناقض بگیرد و تغییر شمارهٔ تماس را بی‌دلیل رد کند
        $student = Student::factory()->create();
        $father = Guardian::factory()->primary()->create(['student_id' => $student->id]);

        $father->update(['mobile' => '09120000000']);

        $this->assertSame('09120000000', $father->fresh()->mobile);
    }

    public function test_a_student_can_have_many_documents(): void
    {
        $student = Student::factory()->create();

        Document::factory()->count(3)->create(['student_id' => $student->id]);

        $this->assertCount(3, $student->documents);
    }

    public function test_a_document_survives_the_deletion_of_the_employee_who_uploaded_it(): void
    {
        // سند پذیرش سند آموزشگاه است نه سند آن کارمند
        $document = Document::factory()->create();

        $document->user->delete();

        $this->assertModelExists($document->fresh());
        $this->assertNull($document->fresh()->user);
    }

    public function test_a_document_is_deleted_with_its_student(): void
    {
        $student = Student::factory()->create();
        Document::factory()->create(['student_id' => $student->id]);

        $student->delete();

        $this->assertSame(0, Document::query()->count());
    }

    public function test_the_student_status_reads_through_from_the_profile(): void
    {
        $student = Student::factory()->create();
        StudentProfile::factory()->create([
            'student_id' => $student->id,
            'status' => Status::OnLeave,
        ]);

        $this->assertSame(Status::OnLeave, $student->fresh()->status);
        $this->assertSame('مرخصی', $student->fresh()->statusLabel());
    }

    public function test_a_student_without_a_profile_has_a_dashed_status_not_a_crash(): void
    {
        $this->assertSame('—', Student::factory()->create()->statusLabel());
    }

    public function test_the_sheba_is_stored_and_shown_unchanged(): void
    {
        $profile = StudentProfile::factory()->create([
            'sheba' => 'IR820540102680020817909002',
        ]);

        $this->assertSame('IR820540102680020817909002', $profile->fresh()->sheba);
    }

    public function test_every_document_type_has_a_persian_label(): void
    {
        foreach (array_keys(Document::types()) as $type) {
            $document = Document::factory()->create(['type' => $type]);

            $this->assertNotSame($type, $document->typeLabel(), $type);
            $this->assertNotSame('', $document->typeLabel());
        }
    }

    public function test_an_unknown_document_type_falls_back_to_its_own_value(): void
    {
        $document = Document::factory()->create(['type' => 'mystery']);

        $this->assertSame('mystery', $document->typeLabel());
    }

    public function test_eager_loading_the_profile_avoids_a_query_per_student(): void
    {
        Student::factory()->count(3)->create();
        StudentProfile::factory()->count(3)->create();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $students = Student::query()->with('profile')->get();
        $students->each(fn (Student $s) => $s->statusLabel());

        // سه دانش‌آموز = سه کوئری، نه یکی به‌ازای هر ردیف
        $this->assertLessThanOrEqual(5, $queries);
    }
}
