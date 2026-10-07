<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private const VALID = [
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'national_code' => '1234567890',
        'father_name' => 'محمد',
        'birth_date' => '1380/05/12',
        'gender' => 'male',
        'mobile' => '09123456789',
        'phone' => '02433445566',
        'email' => 'ali@example.com',
        'guardian_name' => 'محمد رضایی',
        'guardian_mobile' => '09120000000',
        'province' => 'زنجان',
        'city' => 'ابهر',
        'address' => 'خیابان اصلی، پلاک ۱۲',
        'postal_code' => '4513712345',
    ];

    public function test_students_page_renders(): void
    {
        Student::factory()->create(['first_name' => 'مریم', 'last_name' => 'کریمی']);

        $this->get('/students')->assertOk();
    }

    public function test_can_create_a_student_and_shamsi_birth_date_is_stored_as_gregorian(): void
    {
        Livewire::test('pages::students.index')
            ->set(self::VALID)
            ->call('saveStudent')
            ->assertSet('showCreateModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', [
            'first_name' => 'علی',
            'national_code' => '1234567890',
        ]);

        // 1380/05/12 SH must round-trip through the MySQL DATE column
        $this->assertSame(
            '2001-08-03',
            Student::where('national_code', '1234567890')->value('birth_date')
                ?->format('Y-m-d'),
        );
    }

    public function test_create_rejects_an_invalid_mobile_and_bad_national_code(): void
    {
        Livewire::test('pages::students.index')
            ->set(array_merge(self::VALID, [
                'mobile' => '12345',
                'national_code' => '123',
            ]))
            ->call('saveStudent')
            ->assertHasErrors(['mobile', 'national_code']);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_create_rejects_a_malformed_shamsi_date(): void
    {
        Livewire::test('pages::students.index')
            ->set(array_merge(self::VALID, ['birth_date' => '1380-05-12']))
            ->call('saveStudent')
            ->assertHasErrors(['birth_date']);
    }

    public function test_can_update_a_student(): void
    {
        $student = Student::factory()->create();

        Livewire::test('pages::students.index')
            ->call('editStudent', $student->id)
            ->assertSet('showEditModal', true)
            ->assertSet('edit_first_name', $student->first_name)
            ->set('edit_first_name', 'زهرا')
            ->call('updateStudent')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'زهرا',
        ]);
    }

    public function test_edit_form_is_prefilled_with_shamsi_birth_date(): void
    {
        $student = Student::factory()->create(['birth_date' => '2001-08-03']);

        Livewire::test('pages::students.index')
            ->call('editStudent', $student->id)
            ->assertSet('edit_birth_date', '1380/05/12');
    }

    public function test_can_delete_a_student(): void
    {
        $student = Student::factory()->create();

        Livewire::test('pages::students.index')
            ->call('confirmDelete', $student->id)
            ->assertSet('showDeleteModal', true)
            ->call('deleteStudent')
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseCount('students', 0);
    }

    public function test_search_filters_the_list(): void
    {
        Student::factory()->create(['first_name' => 'مریم', 'last_name' => 'کریمی']);
        Student::factory()->create(['first_name' => 'رضا', 'last_name' => 'نوری']);

        Livewire::test('pages::students.index')
            ->set('search', 'مریم')
            ->assertSee('مریم کریمی')
            ->assertDontSee('رضا نوری');
    }

    public function test_list_paginates_ten_per_page(): void
    {
        Student::factory()->count(25)->create();

        Livewire::test('pages::students.index')
            ->assertOk();

        $paginator = Student::query()->paginate(10);

        $this->assertCount(10, $paginator->items());
        $this->assertSame(3, $paginator->lastPage());
    }
}
