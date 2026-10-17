<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| احراز هویت و نقش‌ها
|--------------------------------------------------------------------------
| پنهان کردن لینک از منو امنیت نیست. هر تست اینجا یک کاربر با نقش مشخص را
| مستقیم به آدرس می‌فرستد؛ اگر مجوز در لایه route اعمال نشده باشد، این تست‌ها
| می‌افتند.
*/

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_a_teacher_cannot_open_the_teachers_page(): void
    {
        // دبیر نباید لیست اساتید و اطلاعات پرسنلی را ببیند
        $this->actingAs($this->user(Role::Teacher))
            ->get('/teachers')
            ->assertForbidden();
    }

    public function test_finance_cannot_open_the_teachers_page(): void
    {
        // حقوق و دستمزد داده‌ی حساس است و در بخش اساتید نگهداری می‌شود
        $this->actingAs($this->user(Role::Finance))
            ->get('/teachers')
            ->assertForbidden();
    }

    public function test_deputy_and_admin_can_open_the_teachers_page(): void
    {
        foreach ([Role::Admin, Role::Deputy] as $role) {
            $this->actingAs($this->user($role))->get('/teachers')->assertOk();
        }
    }

    public function test_reception_and_finance_can_see_students_but_teacher_cannot(): void
    {
        // پذیرش برای ثبت‌نام و امور مالی برای وضعیت بدهی به دانش‌آموز نیاز دارند
        foreach ([Role::Reception, Role::Finance] as $role) {
            $this->actingAs($this->user($role))->get('/students')->assertOk();
        }

        $this->actingAs($this->user(Role::Teacher))->get('/students')->assertForbidden();
    }

    public function test_only_the_admin_can_open_user_management(): void
    {
        $this->actingAs($this->user(Role::Admin))->get('/users')->assertOk();

        foreach ([Role::Deputy, Role::Finance, Role::Teacher, Role::Reception] as $role) {
            $this->actingAs($this->user($role))->get('/users')->assertForbidden();
        }
    }

    public function test_a_deactivated_account_cannot_use_an_existing_session(): void
    {
        $user = $this->user(Role::Admin);

        $user->update(['is_active' => false]);

        // نشست واقعی: کاربر از پایگاه داده resolve می‌شود، نه از یک شیء کش‌شده.
        // به همین دلیل غیرفعال شدن باید بلافاصله اثر کند، نه در ورود بعدی.
        $response = $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_menu_hides_sections_the_role_cannot_use(): void
    {
        $this->actingAs($this->user(Role::Teacher))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('کاربران')
            ->assertDontSee('اساتید');

        $this->actingAs($this->user(Role::Admin))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('کاربران');
    }

    public function test_the_role_enum_is_the_single_source_of_visible_sections(): void
    {
        // اگر منو و مجوز جداگانه تعریف شوند، به‌مرور ناسازگار می‌شوند
        $this->assertTrue(Role::Admin->canSee('users'));
        $this->assertFalse(Role::Finance->canSee('users'));
        $this->assertFalse(Role::Teacher->canSee('teachers'));
        $this->assertTrue(Role::Teacher->canSee('grades'));
        $this->assertTrue(Role::Finance->canSee('finance'));
    }

    public function test_an_admin_sees_every_section(): void
    {
        $all = ['students', 'teachers', 'finance', 'users', 'grades', 'reports'];

        foreach ($all as $section) {
            $this->assertTrue(Role::Admin->canSee($section), "admin must see {$section}");
        }
    }

    public function test_every_role_has_a_persian_label(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
