<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // حساب مدیر برای ورود اولیه. رمز پیش‌فرض فقط برای محیط توسعه/داده
        // نمونه است و باید در محیط واقعی عوض شود.
        User::firstOrCreate(
            ['email' => 'admin@academy.test'],
            [
                'name' => 'مدیر آموزشگاه',
                'first_name' => 'مدیر',
                'last_name' => 'آموزشگاه',
                'role' => Role::Admin,
                'password' => bcrypt('password'),
            ],
        );

        // یک نمونه از هر نقش، تا داده نمونه واقع‌نما باشد و مجوزها قابل امتحان
        foreach ([Role::Deputy, Role::Finance, Role::Teacher, Role::Reception] as $role) {
            User::firstOrCreate(
                ['email' => $role->value.'@academy.test'],
                [
                    'name' => $role->label(),
                    'first_name' => $role->label(),
                    'last_name' => 'نمونه',
                    'role' => $role,
                    'password' => bcrypt('password'),
                ],
            );
        }

        $this->call(StudentSeeder::class);
        $this->call(TeacherSeeder::class);
    }
}
