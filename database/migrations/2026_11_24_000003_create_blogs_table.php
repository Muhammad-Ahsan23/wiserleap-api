<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('slug', 140)->unique();
            $table->string('excerpt', 200)->nullable();
            $table->longText('content')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt', 150)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->json('tags')->nullable();
            $table->string('seo_title', 60)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Public listing: published posts, newest first.
            $table->index(['status', 'published_at']);
            // Admin listing: newest edits first.
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
