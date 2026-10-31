<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_only_mark_their_own_notification_as_read(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $notification = $secondUser->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test',
            'data' => ['title' => 'Privado'],
        ]);

        $this->actingAs($firstUser)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->actingAs($secondUser)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}