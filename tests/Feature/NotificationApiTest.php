<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBmsData;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use BuildsBmsData, RefreshDatabase;

    private function notificationFor($user): Notification
    {
        return Notification::create([
            'user_id' => $user->id, 'title' => 'T', 'body' => 'B', 'data' => [], 'read_at' => null,
        ]);
    }

    public function test_user_sees_own_notifications_and_unread_count(): void
    {
        $admin = $this->makeUser('admin');
        $this->notificationFor($admin);
        $this->notificationFor($admin);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications');
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $admin = $this->makeUser('admin');
        $reception = $this->makeUser('reception');
        $note = $this->notificationFor($admin);

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/notifications/{$note->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $admin = $this->makeUser('admin');
        $this->notificationFor($admin);

        $this->actingAs($admin, 'sanctum')->postJson('/api/notifications/read-all')->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 0);
    }
}
