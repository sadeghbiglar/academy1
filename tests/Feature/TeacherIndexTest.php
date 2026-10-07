<?php

namespace Tests\Feature;

use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_teachers_page_renders(): void
    {
        Teacher::factory()->create(['first_name' => 'رضا', 'last_name' => 'محمدی']);

        $this->get('/teachers')->assertOk()->assertSee('محمدی');
    }

    public function test_search_matches_first_last_mobile_and_national_code(): void
    {
        Teacher::factory()->create(['first_name' => 'رضا', 'last_name' => 'محمدی', 'national_code' => '1111111111']);
        Teacher::factory()->create(['first_name' => 'سارا', 'last_name' => 'احمدی', 'national_code' => '2222222222']);

        Livewire::test('pages::teachers.index')->set('search', 'محمدی')->assertSee('محمدی')->assertDontSee('احمدی');

        Livewire::test('pages::teachers.index')->set('search', '2222222222')->assertSee('احمدی')->assertDontSee('محمدی');
    }

    public function test_search_resets_to_the_first_page(): void
    {
        Teacher::factory()->count(25)->create();

        Livewire::test('pages::teachers.index')
            ->call('gotoPage', 2)
            ->set('search', 'a')
            ->assertSet('paginators.page', 1);
    }

    public function test_list_paginates_ten_per_page(): void
    {
        Teacher::factory()->count(25)->create();

        $paginator = Teacher::query()->paginate(10);

        $this->assertCount(10, $paginator->items());
        $this->assertSame(3, $paginator->lastPage());
    }

    public function test_list_is_ordered_newest_first(): void
    {
        $old = Teacher::factory()->create(['first_name' => 'قدیمی']);
        $new = Teacher::factory()->create(['first_name' => 'جدید']);

        $ordered = Teacher::query()->orderByDesc('id')->pluck('id');

        $this->assertSame($new->id, $ordered->first());
        $this->assertSame($old->id, $ordered->last());
    }
}
