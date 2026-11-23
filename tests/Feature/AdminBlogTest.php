<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBlogTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Author $author;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $this->category = Category::create(['name' => 'MVP Development', 'slug' => 'mvp-development']);
        $this->author = Author::create(['name' => 'Muhammad Ahsan', 'bio' => 'Founder']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Scaling Apps On The Cloud',
            'excerpt' => 'Short description',
            'content' => '<h2>Intro</h2><p>Body content here.</p>',
            'image' => UploadedFile::fake()->image('cover.jpg', 1200, 800)->size(300),
            'imageAlt' => 'Cloud diagram',
            'categoryId' => $this->category->id,
            'authorId' => $this->author->id,
            'tags' => ['Cloud', 'cloud', ' DevOps '],
            'seoTitle' => 'Scaling apps',
            'metaDescription' => 'How to scale apps.',
            'status' => 'published',
        ], $overrides);
    }

    public function test_create_published_blog_with_image(): void
    {
        $res = $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'scaling-apps-on-the-cloud')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.tags', ['Cloud', 'DevOps'])
            ->assertJsonPath('data.category.name', 'MVP Development')
            ->assertJsonPath('data.author.name', 'Muhammad Ahsan');

        $this->assertNotNull($res->json('data.publishedAt'));
        $blog = Blog::first();
        Storage::disk('public')->assertExists($blog->image_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'published', 'subject_title' => 'Scaling Apps On The Cloud']);
    }

    public function test_publishing_requires_excerpt_content_image_and_author_but_draft_does_not(): void
    {
        $this->postJson('/api/v1/admin/blogs', $this->payload(['excerpt' => '', 'content' => '<p> </p>', 'image' => null, 'authorId' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['excerpt', 'content', 'image', 'authorId']);

        $this->postJson('/api/v1/admin/blogs', $this->payload(['excerpt' => '', 'content' => '', 'image' => null, 'authorId' => '', 'status' => 'draft']))
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.publishedAt', null);
    }

    public function test_category_and_title_are_always_required(): void
    {
        $this->postJson('/api/v1/admin/blogs', ['title' => 'ab', 'status' => 'draft'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'categoryId']);
    }

    public function test_content_is_sanitised(): void
    {
        $this->post('/api/v1/admin/blogs', $this->payload([
            'content' => '<p onclick="alert(1)">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">x</a><a href="https://wiserleap.com" target="_blank">ok</a><iframe src="//evil"></iframe>',
        ]), ['Accept' => 'application/json'])->assertCreated();

        $html = Blog::first()->content;
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringContainsString('href="https://wiserleap.com"', $html);
        $this->assertStringContainsString('noopener', $html);
    }

    public function test_svg_and_large_images_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/api/v1/admin/blogs', $this->payload(['image' => $svg]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $big = UploadedFile::fake()->image('big.jpg')->size(3000);
        $this->post('/api/v1/admin/blogs', $this->payload(['image' => $big]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_duplicate_slugs_get_a_suffix(): void
    {
        $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.slug', 'scaling-apps-on-the-cloud-2');
    }

    public function test_update_replaces_image_keeps_publish_date_and_logs(): void
    {
        $id = $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])->json('data.id');
        $blog = Blog::find($id);
        $oldImage = $blog->image_path;
        $publishedAt = $blog->published_at->toIso8601String();

        $this->travel(2)->days();
        $this->post("/api/v1/admin/blogs/{$id}", $this->payload([
            '_method' => 'PUT',
            'title' => 'Scaling Apps (Updated)',
            'slug' => 'scaling-apps-updated',
            'image' => UploadedFile::fake()->image('new.png', 800, 600),
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Scaling Apps (Updated)')
            ->assertJsonPath('data.slug', 'scaling-apps-updated')
            ->assertJsonPath('data.publishedAt', $publishedAt);

        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists(Blog::find($id)->image_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'updated']);
    }

    public function test_cannot_publish_after_removing_the_image(): void
    {
        $id = $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])->json('data.id');

        $this->post("/api/v1/admin/blogs/{$id}", $this->payload(['_method' => 'PUT', 'image' => null, 'removeImage' => '1']), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        // Existing image is enough when no new file is sent.
        $this->post("/api/v1/admin/blogs/{$id}", $this->payload(['_method' => 'PUT', 'image' => null]), ['Accept' => 'application/json'])->assertOk();
    }

    public function test_status_toggle(): void
    {
        $id = $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])->json('data.id');

        $this->patchJson("/api/v1/admin/blogs/{$id}/status", ['status' => 'draft'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('activity_logs', ['action' => 'unpublished']);
        $this->patchJson("/api/v1/admin/blogs/{$id}/status", ['status' => 'published'])->assertOk()->assertJsonPath('data.status', 'published');

        $draft = $this->postJson('/api/v1/admin/blogs', $this->payload(['title' => 'Empty draft', 'excerpt' => '', 'content' => '', 'image' => null, 'status' => 'draft']))->json('data.id');
        $this->patchJson("/api/v1/admin/blogs/{$draft}/status", ['status' => 'published'])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_index_filters_search_pagination_and_counts(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/v1/admin/blogs', $this->payload([
                'title' => "Post number {$i}", 'image' => null, 'excerpt' => '', 'content' => '', 'status' => 'draft',
            ]))->assertCreated();
        }
        $this->post('/api/v1/admin/blogs', $this->payload(['title' => 'Cloud costs explained']), ['Accept' => 'application/json'])->assertCreated();

        $this->getJson('/api/v1/admin/blogs')->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('meta.total', 11)
            ->assertJsonPath('meta.counts', ['all' => 11, 'published' => 1, 'draft' => 10])
            ->assertJsonMissingPath('data.0.content');

        $this->getJson('/api/v1/admin/blogs?status=published')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/blogs?search=cloud')->assertJsonCount(1, 'data')->assertJsonPath('meta.counts.all', 1);
        $this->getJson('/api/v1/admin/blogs?search=%25')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/blogs?page=2')->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/admin/blogs?perPage=500')->assertUnprocessable();
        $this->getJson('/api/v1/admin/blogs?categoryId=none')->assertJsonCount(0, 'data');
    }

    public function test_show_and_delete(): void
    {
        $id = $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json'])->json('data.id');
        $image = Blog::find($id)->image_path;

        $this->getJson("/api/v1/admin/blogs/{$id}")->assertOk()->assertJsonPath('data.content', '<h2>Intro</h2><p>Body content here.</p>');
        $this->deleteJson("/api/v1/admin/blogs/{$id}")->assertOk();
        $this->getJson("/api/v1/admin/blogs/{$id}")->assertNotFound();
        Storage::disk('public')->assertMissing($image);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deleted']);
    }

    public function test_dashboard(): void
    {
        $this->post('/api/v1/admin/blogs', $this->payload(), ['Accept' => 'application/json']);
        $this->postJson('/api/v1/admin/blogs', $this->payload(['title' => 'Draft one', 'image' => null, 'status' => 'draft']));
        Blog::where('status', 'published')->update(['views' => 120]);

        $res = $this->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('stats', ['total' => 2, 'published' => 1, 'drafts' => 1, 'views' => 120])
            ->assertJsonCount(6, 'monthly')
            ->assertJsonCount(2, 'recent')
            ->assertJsonCount(2, 'activity');

        $this->assertSame(1, $res->json('monthly.5.count'));
        $this->assertSame(120, $res->json('monthly.5.views'));
    }
}
