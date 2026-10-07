<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_table_exists_with_the_expected_columns(): void
    {
        $columns = Schema::getColumnListing('teachers');

        foreach ([
            'id', 'first_name', 'last_name', 'national_code', 'father_name',
            'birth_date', 'gender', 'mobile', 'phone', 'email', 'subject',
            'hire_date', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertContains($column, $columns);
        }
    }

    public function test_factory_creates_a_teacher(): void
    {
        $teacher = Teacher::factory()->create();

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_fillable_covers_every_migrated_column(): void
    {
        $fillable = (new Teacher)->getFillable();

        foreach ([
            'first_name', 'last_name', 'national_code', 'father_name',
            'birth_date', 'gender', 'mobile', 'phone', 'email', 'subject',
            'hire_date',
        ] as $column) {
            $this->assertContains($column, $fillable);
        }
    }

    public function test_birth_and_hire_dates_are_cast_to_dates(): void
    {
        $teacher = Teacher::factory()->create([
            'birth_date' => '1990-05-12',
            'hire_date' => '2020-03-01',
        ]);

        $this->assertInstanceOf(Carbon::class, $teacher->fresh()->birth_date);
        $this->assertInstanceOf(Carbon::class, $teacher->fresh()->hire_date);
    }
}
