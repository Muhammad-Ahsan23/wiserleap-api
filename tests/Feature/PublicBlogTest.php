<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Blog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    private function makeBlog(string $title, string $status = 'published', ?Category $category = null, ?string $publishedAt = null): Blog
    {
        return Blog::create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'excerpt' => "About {$title}",
            'content' => '<p>'.str_repeat('word ', 450).'</p>',
            'reading_minutes' => 3,
            'image_path' => 'blogs/test.jpg',
            'category_id' => $category?->id,
            'author_id' => Author::firstOrCreate(['name' => 'Muhammad Ahsan'])->id,
            'status' => $status,
            'published_at' => $status === 'published' ? ($publishedAt ?? now()) : null,
        ]);
    }

    public function test_list_shows_only_published_posts_newest_first(): void
    {
        $mvp = Category::create(['name' => 'MVP Development', 'slug' => 'mvp-development']);
        $this->makeBlog('Older post', 'published', $mvp, now()->subDays(5));
        $this->makeBlog('Newer post', 'published', null, now()->subDay());
        $this->makeBlog('Secret draft', 'draft');

        $this->getJson('/api/v1/blogs')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Newer post')
            ->assertJsonPath('data.1.category.slug', 'mvp-development')
            ->assertJsonPath('data.0.readingMinutes', 3)
            ->assertJsonMissingPath('data.0.content')
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.views');

        $this->getJson('/api/v1/blogs?category=mvp-development')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/blogs?search=newer')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/blogs?perPage=100')->assertUnprocessable();
    }

    public function test_show_published_post_with_related_and_hide_drafts(): void
    {
        $this->makeBlog('Main post');
        $this->makeBlog('Another post');
        $this->makeBlog('Secret draft', 'draft');

        $this->getJson('/api/v1/blogs/main-post')->assertOk()
            ->assertJsonPath('data.title', 'Main post')
            ->assertJsonPath('data.seoTitle', 'Main post')
            ->assertJsonCount(1, 'related');

        $this->getJson('/api/v1/blogs/secret-draft')->assertNotFound();
        $this->getJson('/api/v1/blogs/..%2F..%2Fetc')->assertNotFound();
    }

    public function test_cache_is_refreshed_after_admin_changes(): void
    {
        $blog = $this->makeBlog('Cached post');
        $this->getJson('/api/v1/blogs')->assertJsonCount(1, 'data');

        $this->actingAs(User::factory()->create())->patchJson("/api/v1/admin/blogs/{$blog->id}/status", ['status' => 'draft'])->assertOk();

        $this->getJson('/api/v1/blogs')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/blogs/cached-post')->assertNotFound();
    }

    public function test_view_counter_does_not_touch_updated_at_and_is_rate_limited(): void
    {
        $blog = $this->makeBlog('Viewed post');
        $updatedAt = $blog->updated_at->toIso8601String();

        $this->travel(1)->hour();
        $this->postJson('/api/v1/blogs/viewed-post/view')->assertOk();
        $this->assertSame(1, $blog->fresh()->views);
        $this->assertSame($updatedAt, $blog->fresh()->updated_at->toIso8601String());

        $this->postJson('/api/v1/blogs/missing-post/view')->assertNotFound();
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/blogs/viewed-post/view');
        }
        $this->postJson('/api/v1/blogs/viewed-post/view')->assertTooManyRequests();
    }

    public function test_public_categories_only_with_published_posts(): void
    {
        $mvp = Category::create(['name' => 'MVP Development', 'slug' => 'mvp-development']);
        $empty = Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $this->makeBlog('Post A', 'published', $mvp);
        $this->makeBlog('Draft B', 'draft', $empty);

        $this->getJson('/api/v1/categories')->assertOk()->assertExactJson(['data' => [['name' => 'MVP Development', 'slug' => 'mvp-development', 'blogsCount' => 1]]]);
    }
}
