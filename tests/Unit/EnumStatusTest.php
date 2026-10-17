<?php

namespace Tests\Unit;

use App\Enums\Gender;
use App\Enums\Status;
use App\Models\Student;
use App\Models\StudentProfile;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| ثابت‌های وضعیت (شماره 33)
|--------------------------------------------------------------------------
| قانون: هیچ رشتهٔ وضعیتی مستقیم در view یا factory نوشته نشود. این تست علاوه بر
| رفتار enum، خودِ فایل‌ها را هم می‌خواند تا یک رشته جادویی که از راه برگردد را
| لو بدهد.
*/

class EnumStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_view_or_factory_contains_a_magic_gender_string(): void
    {
        $files = array_merge(
            glob(base_path('resources/views/**/*.blade.php')) ?: [],
            glob(base_path('resources/views/**/**/*.blade.php')) ?: [],
            glob(base_path('database/factories/*.php')) ?: [],
        );

        $offenders = [];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);

            foreach (["'male'", "'female'", '"male"', '"female"'] as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = basename($file).' → '.$needle;
                }
            }
        }

        $this->assertSame([], $offenders, 'gender literals must come from App\Enums\Gender');
    }

    public function test_gender_casts_to_the_enum(): void
    {
        $student = Student::factory()->create(['gender' => Gender::Female]);

        $this->assertInstanceOf(Gender::class, $student->gender);
        $this->assertSame('female', $student->gender->value);
        $this->assertSame('دختر', $student->genderLabel());
    }

    public function test_writing_an_unknown_gender_throws(): void
    {
        // نوشتن دادهٔ پرت باید عمداً سخت باشد، نه اینکه بی‌سروصدا رد شود
        $student = Student::factory()->create();

        $this->expectException(\ValueError::class);

        $student->gender = 'unknown';
        $student->save();
    }

    public function test_reading_a_garbage_gender_throws_loudly(): void
    {
        // راه دور زدن Eloquent: import قدیمی یا SQL خام
        //
        // این رفتار درست است، حتی اگر در ابتدا خلاف انتظار به نظر برسد: دادهٔ
        // پرت نباید بی‌سروصدا به null تبدیل و از چشم کاربر پنهان شود. هر
        // ورودی باید از Gender::rule() رد شده باشد تا اصلاً به این نقطه نرسد.
        $student = Student::factory()->create();
        DB::table('students')->where('id', $student->id)->update(['gender' => 'unknown']);

        $this->expectException(\ValueError::class);

        Student::find($student->id)->gender;
    }

    public function test_the_page_survives_a_null_gender(): void
    {
        // null دادهٔ ناقص است، نه دادهٔ پرت؛ نباید صفحه را بترکاند
        $student = Student::factory()->create(['gender' => null]);

        $this->assertSame('—', $student->genderLabel());
    }

    public function test_teacher_gender_also_uses_the_enum(): void
    {
        $teacher = Teacher::factory()->create(['gender' => Gender::Male]);

        $this->assertSame('پسر', $teacher->genderLabel());
    }

    public function test_every_gender_has_a_persian_label_and_options(): void
    {
        foreach (Gender::cases() as $gender) {
            $this->assertNotSame('', $gender->label());
        }

        $this->assertSame(
            [['id' => 'male', 'name' => 'پسر'], ['id' => 'female', 'name' => 'دختر']],
            Gender::options(),
        );
    }

    public function test_the_gender_rule_accepts_only_known_values(): void
    {
        $this->assertTrue(Validator::make(['g' => 'male'], ['g' => ['required', Gender::rule()]])->passes());
        $this->assertTrue(Validator::make(['g' => 'female'], ['g' => ['required', Gender::rule()]])->passes());
        $this->assertTrue(Validator::make(['g' => 'other'], ['g' => ['required', Gender::rule()]])->fails());
        $this->assertTrue(Validator::make(['g' => ''], ['g' => ['required', Gender::rule()]])->fails());
        $this->assertTrue(Validator::make(['g' => ['male']], ['g' => ['required', Gender::rule()]])->fails());
    }

    public function test_status_casts_to_the_enum(): void
    {
        $profile = StudentProfile::factory()->create(['status' => Status::Paid]);

        $this->assertInstanceOf(Status::class, $profile->status);
        $this->assertSame('پرداخت‌شده', $profile->statusLabel());
    }

    public function test_a_new_profile_defaults_to_enrolling_not_active(): void
    {
        // پیش‌فرض «فعال» غلط بود: دانش‌آموزی که ثبت‌نامش نهایی نشده نباید فعال
        // به نظر برسد
        $this->assertSame(Status::Enrolling, StudentProfile::factory()->create()->status);
    }

    public function test_every_status_has_a_label_and_a_badge_color(): void
    {
        foreach (Status::cases() as $status) {
            $this->assertNotSame('', $status->label(), $status->value);
            $this->assertStringStartsWith('badge-', $status->color(), $status->value);
        }
    }

    public function test_status_labels_are_unique(): void
    {
        // دو وضعیت با یک برچسب یعنی کاربر نمی‌تواند تشخیص دهد کدام را می‌بیند
        $labels = array_map(fn (Status $s) => $s->label(), Status::cases());

        $this->assertSame(count($labels), count(array_unique($labels)));
    }

    public function test_outstanding_statuses_are_only_the_unpaid_ones(): void
    {
        $this->assertTrue(Status::Unpaid->isOutstanding());
        $this->assertTrue(Status::Partial->isOutstanding());
        $this->assertFalse(Status::Paid->isOutstanding());
        $this->assertFalse(Status::Refunded->isOutstanding());
        $this->assertFalse(Status::Active->isOutstanding());
    }

    public function test_status_rule_can_be_limited_to_a_subset(): void
    {
        // «غیرفعال» نباید بی‌صدا یک وضعیت مالی معتبر شود
        $rule = Status::rule([Status::Unpaid, Status::Partial, Status::Paid]);

        $this->assertSame('in:unpaid,partial,paid', $rule);
    }

    public function test_label_for_never_throws_on_garbage(): void
    {
        $this->assertSame('—', Status::labelFor(null));
        $this->assertSame('—', Status::labelFor('nonsense'));
        $this->assertSame('—', Gender::labelFor([]));
        $this->assertSame('غیرفعال', Status::labelFor('inactive'));
    }

    public function test_enum_values_are_unique(): void
    {
        foreach ([Gender::cases(), Status::cases()] as $cases) {
            $values = array_column($cases, 'value');

            $this->assertSame(count($values), count(array_unique($values)));
        }
    }
}
