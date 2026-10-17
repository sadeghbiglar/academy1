<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherExperience;
use App\Models\TeacherProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| پروندهٔ استاد: نیم‌رخ پرسنلی، تخصص و سوابق شغلی (شماره 39)
|
| دوره به «استادِ این درس» ارجاع می‌دهد و صفحهٔ تخصیص معلم هم هست — ولی تا
| پیش از این تنها چیزی که از یک استاد می‌دانستیم یک رشتهٔ متنی بود که نه به
| درس‌های آموزشگاه وصل بود، نه به وضعیت استخدام. بدون این سه، انتخاب استاد
| برای یک درس حدس است.
*/

class TeacherRecordTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): Teacher
    {
        return Teacher::factory()->create();
    }

    /* ---------------------------------------------------------------- تخصص */

    public function test_a_teacher_is_linked_to_several_subjects(): void
    {
        $teacher = $this->teacher();
        $math = Subject::factory()->create(['name' => 'ریاضی']);
        $physics = Subject::factory()->create(['name' => 'فیزیک']);

        $teacher->subjects()->attach([$math->id, $physics->id]);

        $this->assertCount(2, $teacher->subjects);
        $this->assertEqualsCanonicalizing(
            ['ریاضی', 'فیزیک'],
            $teacher->subjects->pluck('name')->all()
        );
    }

    public function test_a_subject_can_be_linked_to_several_teachers(): void
    {
        $first = $this->teacher();
        $second = $this->teacher();
        $math = Subject::factory()->create(['name' => 'ریاضی']);

        $math->teachers()->attach([$first->id, $second->id]);

        $this->assertCount(2, $math->teachers);
        $this->assertCount(1, $first->fresh()->subjects);
    }

    public function test_the_same_subject_cannot_be_linked_twice(): void
    {
        $teacher = $this->teacher();
        $math = Subject::factory()->create();

        $teacher->subjects()->attach($math->id);

        $this->expectException(QueryException::class);

        $teacher->subjects()->attach($math->id);
    }

    public function test_detaching_a_subject_keeps_the_teacher(): void
    {
        $teacher = $this->teacher();
        $math = Subject::factory()->create();
        $teacher->subjects()->attach($math->id);

        $teacher->subjects()->detach($math->id);

        $this->assertCount(0, $teacher->fresh()->subjects);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_a_teacher_without_specialties_reports_none(): void
    {
        $teacher = $this->teacher();

        $this->assertCount(0, $teacher->subjects);
        $this->assertSame([], $teacher->specialtyNames());
    }

    /* ------------------------------------------------------------ نیم‌رخ */

    public function test_a_teacher_profile_stores_the_employment_data(): void
    {
        $teacher = $this->teacher();

        $profile = TeacherProfile::create([
            'teacher_id' => $teacher->id,
            'employee_code' => 'T-1001',
            'position' => 'دبیر ارشد',
            'status' => Status::Active,
            'started_at' => '2024-08-02',
            'insurance_number' => '1234567890',
        ]);

        $this->assertSame('T-1001', $profile->employee_code);
        $this->assertSame(Status::Active, $profile->status);
        $this->assertSame('1403/05/12', $profile->startedAtJalali());
        $this->assertSame($profile->id, $teacher->fresh()->profile->id);
    }

    public function test_a_teacher_has_at_most_one_profile(): void
    {
        $teacher = $this->teacher();
        TeacherProfile::create(['teacher_id' => $teacher->id]);

        $this->expectException(QueryException::class);

        TeacherProfile::create(['teacher_id' => $teacher->id]);
    }

    public function test_the_employee_code_is_unique(): void
    {
        TeacherProfile::create(['teacher_id' => $this->teacher()->id, 'employee_code' => 'T-1001']);

        $this->expectException(QueryException::class);

        TeacherProfile::create(['teacher_id' => $this->teacher()->id, 'employee_code' => 'T-1001']);
    }

    public function test_the_employment_cannot_end_before_it_starts(): void
    {
        $teacher = $this->teacher();

        $this->expectException(ValidationException::class);

        TeacherProfile::create([
            'teacher_id' => $teacher->id,
            'started_at' => '2024-08-02',
            'ended_at' => '2024-01-01',
        ]);
    }

    public function test_an_active_profile_cannot_have_an_end_date(): void
    {
        $teacher = $this->teacher();

        $this->expectException(ValidationException::class);

        TeacherProfile::create([
            'teacher_id' => $teacher->id,
            'status' => Status::Active,
            'started_at' => '2024-08-02',
            'ended_at' => '2024-08-02',
        ]);
    }

    public function test_a_profile_defaults_to_active_without_an_end_date(): void
    {
        $profile = TeacherProfile::create([
            'teacher_id' => $this->teacher()->id,
            'started_at' => '2024-08-02',
        ]);

        $this->assertSame(Status::Active, $profile->status);
        $this->assertNull($profile->ended_at);
        $this->assertTrue($profile->isActive());
        $this->assertFalse($profile->isRetired());
    }

    public function test_a_profile_without_a_record_still_reports_a_status(): void
    {
        $teacher = $this->teacher();

        // نبودِ نیم‌رخ یعنی «هنوز استخدام نشده»، نه اینکه statusLabel روی null صدا بزند
        $this->assertNull($teacher->profile);
        $this->assertSame('—', $teacher->statusLabel());
    }

    public function test_retiring_a_profile_requires_an_end_date(): void
    {
        $profile = TeacherProfile::create([
            'teacher_id' => $this->teacher()->id,
            'started_at' => '2024-08-02',
        ]);

        $this->expectException(ValidationException::class);

        // بدون تاریخ پایان، حقوق این استاد هیچ‌وقت قطع نمی‌شود
        $profile->update(['status' => Status::Inactive]);
    }

    public function test_retiring_with_an_end_date_is_allowed(): void
    {
        $profile = TeacherProfile::create([
            'teacher_id' => $this->teacher()->id,
            'started_at' => '2024-08-02',
        ]);

        $profile->update(['status' => Status::Inactive, 'ended_at' => '2025-01-01']);

        $this->assertTrue($profile->fresh()->isRetired());
    }

    public function test_a_teacher_on_leave_keeps_no_end_date(): void
    {
        $profile = TeacherProfile::create([
            'teacher_id' => $this->teacher()->id,
            'status' => Status::OnLeave,
            'started_at' => '2024-08-02',
        ]);

        $this->assertNull($profile->ended_at);
        $this->assertFalse($profile->isActive());
        $this->assertFalse($profile->isRetired());
    }

    public function test_a_teacher_whose_profile_is_retired_is_not_active(): void
    {
        $profile = TeacherProfile::create([
            'teacher_id' => $this->teacher()->id,
            'status' => Status::Inactive,
            'started_at' => '2020-08-02',
            'ended_at' => '2025-01-01',
        ]);

        $this->assertTrue($profile->isRetired());
        $this->assertFalse($profile->isActive());
        $this->assertSame('1403/10/12', $profile->endedAtJalali());
    }

    /* ----------------------------------------------------------- سوابق */

    public function test_a_teacher_can_have_several_experiences(): void
    {
        $teacher = $this->teacher();

        TeacherExperience::create([
            'teacher_id' => $teacher->id,
            'title' => 'دبیر ریاضی',
            'organization' => 'دبیرستان شاداب',
            'started_at' => '2015-09-01',
        ]);

        TeacherExperience::create([
            'teacher_id' => $teacher->id,
            'title' => 'مدرس ریاضی',
            'organization' => 'آموزشگاه نبوغ',
            'started_at' => '2019-09-01',
        ]);

        $this->assertCount(2, $teacher->fresh()->experiences);
    }

    public function test_an_experience_that_ends_before_it_starts_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        TeacherExperience::create([
            'teacher_id' => $this->teacher()->id,
            'title' => 'کارشناس ارشد',
            'started_at' => '2020-09-01',
            'ended_at' => '2018-09-01',
        ]);
    }

    public function test_the_latest_experience_is_current(): void
    {
        $teacher = $this->teacher();

        TeacherExperience::create([
            'teacher_id' => $teacher->id,
            'title' => 'دبیر ریاضی',
            'organization' => 'دبیرستان شاداب',
            'started_at' => '2015-09-01',
            'ended_at' => '2019-09-01',
        ]);

        TeacherExperience::create([
            'teacher_id' => $teacher->id,
            'title' => 'دبیر ارشد',
            'organization' => 'دبیرستان ایران‌دبیر',
            'started_at' => '2019-09-01',
        ]);

        $current = $teacher->currentExperience();

        $this->assertNotNull($current);
        $this->assertSame('دبیر ارشد', $current->title);
        $this->assertTrue($current->isCurrent());
        $this->assertSame('1398/06/10', $current->startedAtJalali());
    }

    public function test_a_teacher_without_experiences_has_no_current_one(): void
    {
        $teacher = $this->teacher();

        $this->assertNull($teacher->currentExperience());
        $this->assertSame('—', $teacher->experiencesLabel());
    }

    public function test_an_experience_can_be_untitled_without_an_organisation(): void
    {
        $experience = TeacherExperience::create([
            'teacher_id' => $this->teacher()->id,
            'title' => 'تدریس آزاد',
            'started_at' => '2021-09-01',
        ]);

        $this->assertNull($experience->organization);
        $this->assertSame('1400/06/10', $experience->startedAtJalali());
        $this->assertSame('تدریس آزاد', $experience->label());
    }

    public function test_deleting_a_teacher_removes_profile_experiences_and_specialties(): void
    {
        $teacher = $this->teacher();
        TeacherProfile::create(['teacher_id' => $teacher->id, 'employee_code' => 'T-2002']);
        TeacherExperience::create([
            'teacher_id' => $teacher->id,
            'title' => 'دبیر',
            'started_at' => '2018-09-01',
        ]);
        $teacher->subjects()->attach(Subject::factory()->create()->id);

        $teacher->delete();

        // رکوردهای یتیم در جدول‌های پرسنلی یعنی گزارش حقوق و سابقه به استاد
        // ناموجود وصل می‌شود
        $this->assertDatabaseMissing('teacher_profiles', ['teacher_id' => $teacher->id]);
        $this->assertDatabaseMissing('teacher_experiences', ['teacher_id' => $teacher->id]);
        $this->assertDatabaseCount('teacher_subjects', 0);
    }
}
