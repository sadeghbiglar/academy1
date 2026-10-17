<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| اتصال حساب دبیر به رکورد استاد
|--------------------------------------------------------------------------
| اگر حساب دبیر به استاد وصل نباشد، «کلاس‌های من» همیشه خالی است و دبیر
| عملاً نمی‌تواند کارش را در سامانه ببیند. یک تست داده نمونه هم ارزش دارد.
*/

class TeacherUserLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_account_carries_a_teacher_id(): void
    {
        $user = User::factory()->role(Role::Teacher)->create();

        $this->assertNotNull($user->teacher_id);
        $this->assertInstanceOf(Teacher::class, $user->teacher);
        $this->assertTrue($user->isTeacher());
    }

    public function test_a_teacher_sees_only_their_own_courses(): void
    {
        $user = User::factory()->role(Role::Teacher)->create();

        Course::factory()->create(['teacher_id' => $user->teacher_id]);
        Course::factory()->create(['teacher_id' => $user->teacher_id]);

        // صریحاً استاد دیگری، چون factory وقتی استاد موجود باشد به یکی تصادفی
        // وصل می‌کند و «۲ تا در برابر ۲ تا» مبهم می‌شد
        Course::factory()->create(['teacher_id' => Teacher::factory()->create()]);

        $this->assertSame(2, $user->teacher->courses()->count());
    }

    public function test_a_non_teacher_account_has_no_teacher_record(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertNull($admin->teacher_id);
        $this->assertNull($admin->teacher);
        $this->assertFalse($admin->isTeacher());
    }

    public function test_the_seeder_links_the_teacher_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', Role::Teacher->value)->first();

        $this->assertNotNull($user, 'no teacher account was seeded');
        $this->assertNotNull($user->teacher_id, 'the seeded teacher account is not linked');
        $this->assertNotSame(0, $user->teacher->courses()->count());
    }

    public function test_seeding_twice_does_not_reassign_the_teacher(): void
    {
        $this->seed(DatabaseSeeder::class);

        $first = User::query()->where('role', Role::Teacher->value)->first()->teacher_id;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, User::query()->where('role', Role::Teacher->value)->first()->teacher_id);
    }
}
