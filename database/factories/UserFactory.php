<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // پیش‌فرض «پذیرش» است، نه «مدیر»: یک factory که به‌طور پیش‌فرض
            // بیشترین دسترسی را بدهد، تست‌های مجوز را بی‌معنا می‌کند. تستی که
            // به مدیر نیاز دارد باید صریحاً بگوید admin().
            'role' => Role::Reception,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mobile' => fake()->numerify('091########'),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    /**
     * حساب دبیر باید به یک رکورد استاد وصل باشد.
     *
     * بدون این اتصال، «کلاس‌های من» همیشه خالی است و هیچ تستی که با نقش
     * دبیر کار می‌کند نمی‌تواند بفهمد داده درست است یا نه.
     */
    public function teacher(): static
    {
        return $this->state(fn () => [
            'role' => Role::Teacher,
            'teacher_id' => Teacher::factory(),
        ]);
    }

    /** کاربر مدیر — همه بخش‌ها را می‌بیند. */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function role(Role $role): static
    {
        return $this->state(function () use ($role) {
            return $role === Role::Teacher
                ? ['role' => $role, 'teacher_id' => Teacher::factory()]
                : ['role' => $role, 'teacher_id' => null];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
