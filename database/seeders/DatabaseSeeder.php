<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Teacher;
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

        // ترتیب مهم است: دوره‌ها به درس و استاد وصل می‌شوند، پس هر دو باید
        // قبل از آن‌ها وجود داشته باشند وگرنه هر دوره یک درس و یک استاد
        // یک‌بارمصرف خودش می‌سازد
        $this->call(TeacherSeeder::class);

        // حساب دبیر باید به رکورد استاد وصل باشد، وگرنه «کلاس‌های من» برای او
        // همیشه خالی است و دبیر نمی‌تواند کارش را ببیند
        $teacherUser = User::query()->where('role', Role::Teacher)->first();

        if ($teacherUser && ! $teacherUser->teacher_id) {
            $teacherUser->update(['teacher_id' => Teacher::query()->value('id')]);
        }
        $this->call(SubjectSeeder::class);
        $this->call(StudentSeeder::class);
    }
}
