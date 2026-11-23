<?php

namespace App\Support;

use App\Services\HtmlSanitizer;

/**
 * What a blog needs before it can be published (same rules as the admin panel).
 */
class PublishChecklist
{
    /**
     * @param  array{excerpt?: ?string, content?: ?string, authorId?: mixed, categoryId?: mixed, hasImage: bool}  $blog
     * @return array<string, string> field => message
     */
    public static function missing(array $blog): array
    {
        $missing = [];

        if (blank($blog['excerpt'] ?? null)) {
            $missing['excerpt'] = 'Add a short description before publishing.';
        }
        if (HtmlSanitizer::isEmpty($blog['content'] ?? null)) {
            $missing['content'] = 'Write some content before publishing.';
        }
        if (! $blog['hasImage']) {
            $missing['image'] = 'Add a featured image before publishing.';
        }
        if (array_key_exists('categoryId', $blog) && blank($blog['categoryId'])) {
            $missing['categoryId'] = 'Choose a category before publishing.';
        }
        if (blank($blog['authorId'] ?? null)) {
            $missing['authorId'] = 'Choose an author before publishing.';
        }

        return $missing;
    }
}
