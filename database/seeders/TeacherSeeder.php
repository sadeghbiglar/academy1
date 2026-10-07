<?php

namespace Database\Seeders;

use App\Models\Teacher;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /**
     * How many teachers to seed.
     */
    private const COUNT = 15;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existing = Teacher::count();

        if ($existing >= self::COUNT) {
            $this->command?->info("Teachers already seeded, skipping ({$existing} rows).");

            return;
        }

        Teacher::factory()->count(self::COUNT - $existing)->create();

        $this->command?->info('Seeded '.Teacher::count().' teachers total.');
    }
}
