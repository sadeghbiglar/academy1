<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * How many students to seed.
     */
    private const COUNT = 20;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existing = Student::count();

        if ($existing >= self::COUNT) {
            $this->command?->info("Students already seeded, skipping ({$existing} rows).");

            return;
        }

        Student::factory()->count(self::COUNT - $existing)->create();

        $this->command?->info('Seeded '.Student::count().' students total.');
    }
}
