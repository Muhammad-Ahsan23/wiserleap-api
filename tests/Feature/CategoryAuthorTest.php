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

class CategoryAuthorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    public function test_category_crud_and_case_insensitive_duplicates(): void
    {
        $id = $this->postJson('/api/v1/admin/categories', ['name' => '  Cloud   &  DevOps '])
            ->assertCreated()->assertJsonPath('data.name', 'Cloud & DevOps')->assertJsonPath('data.slug', 'cloud-devops')->assertJsonPath('data.blogsCount', 0)
            ->json('data.id');

        $this->postJson('/api/v1/admin/categories', ['name' => 'cloud & devops'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'A category called "cloud & devops" already exists.');
        $this->postJson('/api/v1/admin/categories', ['name' => 'x'])->assertUnprocessable();

        // Renaming to the same name (other case) is allowed for the same row.
        $this->putJson("/api/v1/admin/categories/{$id}", ['name' => 'Cloud Engineering'])->assertOk()->assertJsonPath('data.slug', 'cloud-engineering');

        $this->getJson('/api/v1/admin/categories')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_deleting_a_category_uncategorises_its_blogs(): void
    {
        $category = Category::create(['name' => 'Insights', 'slug' => 'insights']);
        $blog = Blog::create(['title' => 'A post', 'slug' => 'a-post', 'category_id' => $category->id, 'status' => 'draft']);

        $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertOk()->assertJsonPath('affectedBlogs', 1);
        $this->assertNull($blog->fresh()->category_id);
    }

    public function test_author_crud_with_photo(): void
    {
        $res = $this->post('/api/v1/admin/authors', [
            'name' => 'Sara Khan',
            'bio' => 'Content Strategist',
            'image' => UploadedFile::fake()->image('sara.png', 400, 400)->size(200),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.name', 'Sara Khan');

        $id = $res->json('data.id');
        $photo = Author::find($id)->image_path;
        Storage::disk('public')->assertExists($photo);
        $this->assertStringContainsString('/storage/authors/', $res->json('data.image'));

        $this->post("/api/v1/admin/authors/{$id}", ['_method' => 'PUT', 'name' => 'Sara Khan', 'bio' => 'Senior Content Strategist', 'removeImage' => '1'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.bio', 'Senior Content Strategist')->assertJsonPath('data.image', null);
        Storage::disk('public')->assertMissing($photo);

        $this->postJson('/api/v1/admin/authors', ['name' => 'sara khan'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/admin/authors', ['name' => 'Long bio', 'bio' => str_repeat('a', 201)])->assertUnprocessable()->assertJsonValidationErrors('bio');
        $this->post('/api/v1/admin/authors', ['name' => 'Big photo', 'image' => UploadedFile::fake()->image('big.jpg')->size(900)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_deleting_an_author_keeps_their_blogs(): void
    {
        $author = Author::create(['name' => 'Old Author']);
        $blog = Blog::create(['title' => 'A post', 'slug' => 'a-post', 'author_id' => $author->id, 'status' => 'draft']);

        $this->deleteJson("/api/v1/admin/authors/{$author->id}")->assertOk()->assertJsonPath('affectedBlogs', 1);
        $this->assertNull($blog->fresh()->author_id);
    }
}
