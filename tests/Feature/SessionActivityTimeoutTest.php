<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionActivityTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_polls_do_not_extend_the_interaction_timeout(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->getJson(route('realtime.version'))->assertOk();

        $this->travel(60)->minutes();
        $this->getJson(route('realtime.version'))->assertOk();

        $this->travel(61)->minutes();
        $this->getJson(route('realtime.version'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_reported_user_activity_extends_the_interaction_timeout(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->get(route('profile'))->assertOk();

        $this->travel(60)->minutes();
        $this->withHeader('X-User-Activity', '1')->getJson(route('realtime.version'))->assertOk();

        $this->travel(61)->minutes();
        $this->getJson(route('realtime.version'))->assertOk();
    }

    public function test_expired_page_request_redirects_to_login_with_a_message(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->get(route('profile'))->assertOk();

        $this->travel(121)->minutes();
        $this->get(route('profile'))->assertRedirect(route('login', ['expired' => 1]));
        $this->assertGuest();
        $this->get(route('login', ['expired' => 1]))->assertSee('Your session expired due to inactivity.');
    }
}
