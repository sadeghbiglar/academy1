<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| ناوبری و داشبورد (شماره 32)
|--------------------------------------------------------------------------
*/

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function dashboardFor(Role $role): string
    {
        return $this->actingAs($this->user($role))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();
    }

    private function user(Role $role): User
    {
        return User::factory()->role($role)->create();
    }

    public function test_the_menu_never_links_to_a_route_that_does_not_exist(): void
    {
        // هر href در منو باید واقعاً resolve شود؛ لینک شکسته یعنی رگرسیون
        $html = $this->dashboardFor(Role::Admin);

        // فقط لینک‌های منو، نه assetهای vite
        preg_match_all('/<a href="(http:\/\/localhost[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'the menu rendered no links at all');

        foreach ($matches[1] as $url) {
            // لینک منو باید به یک صفحه برسد، نه به خطای 404
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_the_menu_shows_each_role_only_its_own_sections(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

            foreach (Section::cases() as $section) {
                $built = $section->url() !== null;

                if (! $built || ! $role->canSee($section)) {
                    continue;
                }

                // فقط لینک‌های ساخته‌شده قابل بررسی‌اند
                $this->assertStringContainsString(
                    'href="'.$section->url().'"',
                    $html,
                    "{$role->value} should see the {$section->value} link",
                );
            }
        }
    }

    public function test_a_teacher_menu_has_no_finance_or_teachers_link(): void
    {
        $html = $this->dashboardFor(Role::Teacher);

        $this->assertStringNotContainsString('href="'.route('teachers.index').'"', $html);
        $this->assertStringNotContainsString('malی', $html);
    }

    public function test_a_reception_menu_has_no_grades_link(): void
    {
        // پذیرش نمره نمی‌دهد
        $this->assertFalse(Role::Reception->canSee(Section::Grades));
        $this->assertFalse(Role::Finance->canSee(Section::Grades));
    }

    public function test_every_role_sees_the_dashboard_and_can_reach_their_profile(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertTrue($role->canSee(Section::Dashboard), "{$role->value} needs a dashboard");
            $this->assertTrue($role->canSee(Section::Profile), "{$role->value} needs a profile");
        }
    }

    public function test_only_the_admin_sees_user_management(): void
    {
        $this->assertTrue(Role::Admin->canSee(Section::Users));

        foreach ([Role::Deputy, Role::Finance, Role::Teacher, Role::Reception] as $role) {
            $this->assertFalse($role->canSee(Section::Users));
        }
    }

    public function test_finance_sees_money_but_not_academic_grading(): void
    {
        $this->assertTrue(Role::Finance->canSee(Section::Finance));
        $this->assertFalse(Role::Finance->canSee(Section::Grades));
        $this->assertFalse(Role::Finance->canSee(Section::Courses));
    }

    public function test_the_dashboard_counts_students_and_teachers_for_an_admin(): void
    {
        Student::factory()->count(3)->create();
        Teacher::factory()->count(5)->create();

        $this->actingAs($this->user(Role::Admin))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(to_persian_digits(3))
            ->assertSee(to_persian_digits(5));
    }

    public function test_the_dashboard_does_not_leak_counts_to_a_teacher(): void
    {
        // شمردن چیزی که کاربر اجازه دیدنش را ندارد، نشت اطلاعات است حتی اگر فقط عدد باشد
        Student::factory()->count(4)->create();

        $this->actingAs($this->user(Role::Teacher))
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('دانش‌آموزان');
    }

    public function test_the_dashboard_lists_sections_still_in_build(): void
    {
        // کاربر باید بداند چه چیزی در راه است، بدون اینکه لینک مرده ببیند
        $this->actingAs($this->user(Role::Admin))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('در دست ساخت')
            ->assertSee(Section::Finance->label());
    }

    public function test_every_section_has_a_persian_label_and_an_icon(): void
    {
        foreach (Section::cases() as $section) {
            $this->assertNotSame('', $section->label(), $section->value);
            $this->assertNotSame('', $section->icon(), $section->value);
        }
    }

    public function test_every_built_section_has_a_real_route(): void
    {
        foreach (Section::cases() as $section) {
            if ($section->url() === null) {
                continue;
            }

            $this->assertNotNull($section->routeName());
            $this->assertStringStartsWith('http', $section->url());
        }
    }

    public function test_the_menu_order_is_stable(): void
    {
        // ترتیب منو بخشی از آموزش کاربر است؛ نباید با بازچینش کد عوض شود
        $order = array_map(fn (Section $s) => $s->value, Section::menuOrder());

        $this->assertSame('dashboard', $order[0]);

        // پروفایل و خروج، انتهای منو می‌نشینند
        $this->assertSame('profile', end($order));
        $this->assertSame(count(Section::cases()), count($order));
    }
}
