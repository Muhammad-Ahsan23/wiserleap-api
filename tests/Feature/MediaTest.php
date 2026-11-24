<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\MediaItem;
use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Ahsan']);
        $this->actingAs($user);

        return $user;
    }

    public function test_media_routes_require_login(): void
    {
        $this->getJson('/api/v1/admin/media')->assertUnauthorized();
        $this->postJson('/api/v1/admin/media', [])->assertUnauthorized();
    }

    public function test_upload_is_optimised_to_webp_and_listed(): void
    {
        $this->admin();
        $file = UploadedFile::fake()->image('Team Photo.jpg', 3000, 1500);

        $response = $this->post('/api/v1/admin/media', ['file' => $file, 'alt' => 'Our team'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Team Photo')
            ->assertJsonPath('data.alt', 'Our team')
            ->assertJsonPath('data.uploadedBy', 'Ahsan');

        $item = MediaItem::sole();
        if (\App\Support\ImageOptimizer::canOptimize()) {
            $this->assertSame('image/webp', $item->mime);
            $this->assertStringEndsWith('.webp', $item->path);
            $this->assertSame(2000, $item->width); // scaled down to MAX_WIDTH
            $this->assertSame(1000, $item->height);
        }
        Storage::disk('public')->assertExists($item->path);
        $this->assertStringContainsString('/storage/'.$item->path, $response->json('data.url'));

        $this->getJson('/api/v1/admin/media')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/media?search=team')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/media?search=nothing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_small_images_keep_their_size(): void
    {
        $this->admin();
        $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('small.png', 640, 480)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.width', 640)->assertJsonPath('data.height', 480);
    }

    public function test_rejects_non_images_svg_and_large_files(): void
    {
        $this->admin();
        $post = fn ($file) => $this->post('/api/v1/admin/media', ['file' => $file], ['Accept' => 'application/json']);

        $post(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $post(UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'))->assertUnprocessable();
        $post(UploadedFile::fake()->image('huge.jpg', 100, 100)->size(6000))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/api/v1/admin/media', [])->assertUnprocessable()->assertJsonPath('errors.file.0', 'Choose an image to upload.');
        $this->assertDatabaseCount('media_items', 0);
    }

    public function test_alt_text_can_be_edited(): void
    {
        $this->admin();
        $id = $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])->json('data.id');

        $this->patchJson("/api/v1/admin/media/{$id}", ['alt' => 'A chart'])->assertOk()->assertJsonPath('data.alt', 'A chart');
        $this->patchJson("/api/v1/admin/media/{$id}", ['alt' => str_repeat('a', 151)])->assertUnprocessable();
    }

    public function test_delete_warns_when_the_image_is_used_in_a_post(): void
    {
        $this->admin();
        $data = $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])->json('data');
        $item = MediaItem::find($data['id']);

        Blog::create(['title' => 'Uses it', 'slug' => 'uses-it', 'status' => 'draft', 'content' => '<figure class="wl-figure"><img src="'.$data['url'].'" alt=""></figure>']);

        $this->deleteJson("/api/v1/admin/media/{$item->id}")
            ->assertStatus(409)
            ->assertJsonPath('usedIn.0.title', 'Uses it');
        Storage::disk('public')->assertExists($item->path);

        $this->deleteJson("/api/v1/admin/media/{$item->id}?force=1")->assertOk();
        Storage::disk('public')->assertMissing($item->path);
        $this->assertDatabaseCount('media_items', 0);
    }

    public function test_unused_image_deletes_straight_away(): void
    {
        $this->admin();
        $id = $this->post('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('a.jpg', 50, 50)], ['Accept' => 'application/json'])->json('data.id');
        $this->deleteJson("/api/v1/admin/media/{$id}")->assertOk();
    }

    public function test_sanitizer_keeps_editor_formatting_and_drops_everything_else(): void
    {
        $own = rtrim((string) config('app.url'), '/').'/storage/content/2026/11/a.webp';
        $html = (new HtmlSanitizer)->clean(
            '<p style="text-align:center;color:#4d278d;position:fixed;background:url(x)">Hi <mark>there</mark> <span style="color:#a572ff">c</span></p>'
            .'<figure class="wl-figure wl-figure--medium wl-figure--center evil" onclick="x"><img src="'.$own.'" alt="A" width="800" height="600" onerror="alert(1)"><figcaption>Cap</figcaption></figure>'
            .'<figure class="wl-figure"><img src="https://evil.example/pixel.gif"></figure>'
            .'<img src="javascript:alert(1)"><h2 style="text-align:right">T</h2><script>x</script><iframe src="x"></iframe>'
        );

        $this->assertStringContainsString('<p style="text-align:center;color:#4d278d;">', $html);
        $this->assertStringContainsString('<mark>there</mark>', $html);
        $this->assertStringContainsString('<figure class="wl-figure wl-figure--medium wl-figure--center"><img src="'.$own.'" alt="A" width="800" height="600"><figcaption>Cap</figcaption></figure>', $html);
        $this->assertStringContainsString('<h2 style="text-align:right;">T</h2>', $html);
        foreach (['evil', 'onclick', 'onerror', 'position', 'url(', 'javascript', '<script', '<iframe', 'pixel.gif'] as $bad) {
            $this->assertStringNotContainsString($bad, $html);
        }
    }

    public function test_blog_content_with_figures_is_saved_cleaned(): void
    {
        $this->admin();
        $own = rtrim((string) config('app.url'), '/').'/storage/content/x.webp';

        $category = \App\Models\Category::create(['name' => 'Insights', 'slug' => 'insights']);
        $this->post('/api/v1/admin/blogs', [
            'title' => 'Figure post', 'slug' => 'figure-post', 'status' => 'draft', 'categoryId' => $category->id,
            'content' => '<p>Intro</p><figure class="wl-figure wl-figure--full"><img src="'.$own.'" alt="X" onerror="alert(1)"><figcaption>Caption</figcaption></figure>',
        ], ['Accept' => 'application/json'])->assertCreated();

        $content = Blog::sole()->content;
        $this->assertStringContainsString('<figcaption>Caption</figcaption>', $content);
        $this->assertStringNotContainsString('onerror', $content);
    }
}
