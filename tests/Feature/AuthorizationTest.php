<?php

namespace Tests\Feature;

use App\Models\CopilotUser;
use App\Models\DailyUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $admin = false): User
    {
        return User::factory()->create([
            'github_id'    => (string) fake()->numberBetween(1000, 99999),
            'github_login' => fake()->userName(),
            'is_admin'     => $admin,
        ]);
    }

    public function test_unauthenticated_redirects_to_github_login(): void
    {
        $this->get('/')->assertRedirect(route('auth.github'));
    }

    public function test_regular_user_sees_dashboard(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_org(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/org')
            ->assertForbidden();
    }

    public function test_admin_can_access_org(): void
    {
        $this->actingAs($this->makeUser(admin: true))
            ->get('/org')
            ->assertOk();
    }

    public function test_admin_can_view_member_detail(): void
    {
        $copilotUser = CopilotUser::create([
            'github_id'    => '1',
            'github_login' => 'alice',
        ]);

        $this->actingAs($this->makeUser(admin: true))
            ->get('/org/members/alice')
            ->assertOk();
    }

    public function test_member_not_found_returns_404(): void
    {
        $this->actingAs($this->makeUser(admin: true))
            ->get('/org/members/nonexistent-user')
            ->assertNotFound();
    }
}
