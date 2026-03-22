<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Models\DocumentHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_returns_paginated_notifications(): void
    {
        $doc = DocumentHeader::factory()->invoice()->draft()->create(['user_id' => $this->admin->id]);
        $this->admin->notify(new OrderConfirmation($doc));

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/notifications')
             ->assertOk()
             ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_unread_returns_count_and_data(): void
    {
        $initialCount = $this->admin->unreadNotifications()->count();

        $doc = DocumentHeader::factory()->invoice()->draft()->create(['user_id' => $this->admin->id]);
        $this->admin->notify(new OrderConfirmation($doc));

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/notifications/unread');

        $response->assertOk()
                 ->assertJsonStructure(['count', 'data']);

        $this->assertEquals($initialCount + 1, $response->json('count'));
    }

    public function test_mark_as_read(): void
    {
        $doc = DocumentHeader::factory()->invoice()->draft()->create(['user_id' => $this->admin->id]);
        $this->admin->notify(new OrderConfirmation($doc));

        $notif = $this->admin->unreadNotifications()->latest()->first();

        $this->actingAs($this->admin, 'sanctum')
             ->patchJson("/api/notifications/{$notif->id}/read")
             ->assertOk();

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $doc1 = DocumentHeader::factory()->invoice()->draft()->create(['user_id' => $this->admin->id]);
        $doc2 = DocumentHeader::factory()->invoice()->draft()->create(['user_id' => $this->admin->id]);
        $this->admin->notify(new OrderConfirmation($doc1));
        $this->admin->notify(new OrderConfirmation($doc2));

        $this->assertGreaterThanOrEqual(2, $this->admin->unreadNotifications()->count());

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/notifications/mark-all-read')
             ->assertOk();

        $this->assertEquals(0, $this->admin->fresh()->unreadNotifications()->count());
    }

    public function test_unauthenticated_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')
             ->assertUnauthorized();
    }

    public function test_notification_belongs_to_user_only(): void
    {
        $otherUser = User::factory()->manager()->create();
        $doc       = DocumentHeader::factory()->invoice()->draft()->create(['user_id' => $otherUser->id]);
        $otherUser->notify(new OrderConfirmation($doc));

        $initialCount = $this->admin->unreadNotifications()->count();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/notifications/unread');

        $response->assertOk()
                 ->assertJsonFragment(['count' => $initialCount]);
    }
}
