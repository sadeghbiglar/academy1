<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_id' => Student::factory(),
            'type' => fake()->randomElement(array_keys(Document::types())),
            'title' => fake()->randomElement(['کارت ملی', 'شناسنامه', 'عکس ۳×۴']),
            'file_path' => 'documents/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 900_000),
            'document_date' => fake()->dateTimeBetween('-3 years', 'now'),
        ];
    }
}
