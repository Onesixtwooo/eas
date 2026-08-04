<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_receive_a_new_revision_after_system_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $initial = $this->actingAs($admin)
            ->getJson(route('realtime.version'))
            ->assertOk()
            ->json('revision');

        $this->actingAs($admin)->put(route('profile.update'), [
            'name' => 'Updated Administrator',
            'email' => $admin->email,
        ])->assertSessionHasNoErrors();

        $updated = $this->actingAs($admin)
            ->getJson(route('realtime.version'))
            ->assertOk()
            ->json('revision');

        $this->assertNotSame($initial, $updated);
    }

    public function test_realtime_revision_is_not_public(): void
    {
        $this->getJson(route('realtime.version'))->assertUnauthorized();
    }
}
