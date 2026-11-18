<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SnipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['snipo.disk' => 'local']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/record')->assertRedirect('/login');
        $this->post('/videos', ['mime' => 'video/webm'])->assertRedirect('/login');
    }

    public function test_register_login_logout(): void
    {
        $this->post('/register', [
            'name' => 'Ana', 'email' => 'ana@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect('/');
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'wrong-pass'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'secret123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_full_upload_flow_and_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $slug = $this->actingAs($owner)->postJson('/videos', ['title' => 'Demo', 'mime' => 'video/webm'])
            ->assertOk()->json('slug');

        $part1 = UploadedFile::fake()->createWithContent('chunk', str_repeat('a', 1000));
        $part2 = UploadedFile::fake()->createWithContent('chunk', str_repeat('b', 500));
        $this->postJson("/videos/$slug/chunk", ['index' => 0, 'chunk' => $part1])->assertOk();
        $this->postJson("/videos/$slug/chunk", ['index' => 5, 'chunk' => $part2])->assertStatus(409)->assertJson(['expected' => 1]);
        $this->postJson("/videos/$slug/chunk", ['index' => 1, 'chunk' => $part2])->assertOk();

        // Someone else can't touch it.
        $this->actingAs($other)->postJson("/videos/$slug/finish")->assertForbidden();
        $this->actingAs($other)->patchJson("/videos/$slug", ['title' => 'Hacked'])->assertForbidden();
        $this->actingAs($other)->delete("/videos/$slug")->assertForbidden();

        $this->actingAs($owner)->postJson("/videos/$slug/finish", ['duration' => 12.5])->assertOk();
        $video = Video::where('slug', $slug)->first();
        $this->assertSame('ready', $video->status);
        $this->assertSame(1500, (int) $video->size);
        Storage::disk('local')->assertExists("videos/$slug.webm");
        $this->assertSame(str_repeat('a', 1000).str_repeat('b', 500), Storage::disk('local')->get("videos/$slug.webm"));
        $this->assertFileDoesNotExist($video->tempPath());

        // Library shows only your own videos.
        $this->actingAs($owner)->get('/')->assertSee('Demo');
        $this->actingAs($other)->get('/')->assertDontSee('Demo');

        // Share page is public; only the owner sees Delete.
        auth()->logout();
        $this->get("/v/$slug")->assertOk()->assertSee('Demo')->assertDontSee('Delete');
        $this->get("/v/$slug/file")->assertOk();
        $this->actingAs($owner)->get("/v/$slug")->assertSee('Delete');

        // Owner can delete; file is removed.
        $this->actingAs($owner)->delete("/videos/$slug")->assertRedirect('/');
        Storage::disk('local')->assertMissing("videos/$slug.webm");
        $this->assertNull(Video::where('slug', $slug)->first());
    }

    public function test_size_limit_is_enforced(): void
    {
        config(['snipo.max_mb' => 1]);
        $user = User::factory()->create();
        $slug = $this->actingAs($user)->postJson('/videos', ['mime' => 'video/webm'])->json('slug');

        $big = UploadedFile::fake()->create('chunk', 1100); // 1.1 MB
        $this->postJson("/videos/$slug/chunk", ['index' => 0, 'chunk' => $big])->assertStatus(413);
    }

    public function test_empty_upload_is_rejected(): void
    {
        $user = User::factory()->create();
        $slug = $this->actingAs($user)->postJson('/videos', ['mime' => 'video/webm'])->json('slug');
        $this->postJson("/videos/$slug/finish")->assertStatus(422);
    }
}
