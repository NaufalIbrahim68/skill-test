<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_paginated_active_posts()
    {
        $user = User::factory()->create();
        Post::factory()->count(25)->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        // Draft post
        Post::factory()->create([
            'is_draft' => true,
        ]);

        // Scheduled post
        Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/posts');

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 25);
    }

    public function test_can_show_active_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $post->id);
    }

    public function test_cannot_show_draft_or_scheduled_post()
    {
        $user = User::factory()->create();
        $draft = Post::factory()->create(['user_id' => $user->id, 'is_draft' => true]);
        $scheduled = Post::factory()->create([
            'user_id' => $user->id,
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $this->getJson("/posts/{$draft->id}")->assertStatus(404);
        $this->getJson("/posts/{$scheduled->id}")->assertStatus(404);
    }

    public function test_only_authenticated_users_can_create_posts()
    {
        $this->get('/posts/create')->assertRedirect('/login');
        $this->postJson('/posts', ['title' => 'Test', 'content' => 'Test'])->assertStatus(401);
    }

    public function test_authenticated_user_can_create_post()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/posts/create')
            ->assertStatus(200)
            ->assertSeeText('posts.create');

        $response = $this->actingAs($user)->postJson('/posts', [
            'title' => 'Test Title',
            'content' => 'Test Content',
            'is_draft' => false,
            'published_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Test Title');
    }

    public function test_only_author_can_edit_update_delete_post()
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        // Other user cannot edit
        $this->actingAs($otherUser)->get("/posts/{$post->id}/edit")->assertStatus(403);
        $this->actingAs($otherUser)->putJson("/posts/{$post->id}", ['title' => 'New Title'])->assertStatus(403);
        $this->actingAs($otherUser)->deleteJson("/posts/{$post->id}")->assertStatus(403);

        // Author can
        $this->actingAs($author)->get("/posts/{$post->id}/edit")
            ->assertStatus(200)
            ->assertSeeText('posts.edit');

        $this->actingAs($author)->putJson("/posts/{$post->id}", ['title' => 'Updated Title'])
            ->assertStatus(200)
            ->assertJsonPath('title', 'Updated Title');

        $this->actingAs($author)->deleteJson("/posts/{$post->id}")
            ->assertStatus(204);
    }
}
