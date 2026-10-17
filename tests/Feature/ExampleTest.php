<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_url_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/students')->assertRedirect('/login');
    }

    public function test_the_dashboard_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('داشبورد', escape: false);
    }
}
