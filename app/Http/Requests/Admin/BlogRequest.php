<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlogStatus;
use App\Models\Blog;
use App\Support\PublishChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/**
 * Create and update a blog (multipart/form-data because of the image).
 * Updates are sent as POST with `_method=PUT`.
 */
class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum; every account is an admin
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:200000'],
            'image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(config('media.blog_image_max_kb'))->dimensions(Rule::dimensions()->maxWidth(6000)->maxHeight(6000))],
            'removeImage' => ['sometimes', 'boolean'],
            'imageAlt' => ['nullable', 'string', 'max:150'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'authorId' => ['nullable', 'integer', 'exists:authors,id'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30', 'distinct:ignore_case'],
            'seoTitle' => ['nullable', 'string', 'max:60'],
            'metaDescription' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::enum(BlogStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens.',
            'categoryId.required' => 'Choose a category.',
            'image.max' => 'Use an image under '.round(config('media.blog_image_max_kb') / 1024, 1).' MB.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('status') !== BlogStatus::Published->value) {
                    return;
                }

                /** @var Blog|null $blog */
                $blog = $this->route('blog');
                $hasImage = $this->hasFile('image') || ($blog?->image_path && ! $this->boolean('removeImage'));

                foreach (PublishChecklist::missing([
                    'excerpt' => $this->input('excerpt'),
                    'content' => $this->input('content'),
                    'authorId' => $this->input('authorId'),
                    'hasImage' => (bool) $hasImage,
                ]) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        // Strings are already trimmed and "" becomes null (Laravel middleware).
        $this->merge([
            'tags' => collect((array) $this->input('tags', []))
                ->map(fn ($tag) => trim((string) $tag))
                ->filter()
                ->unique(fn ($tag) => mb_strtolower($tag))
                ->values()
                ->all(),
        ]);
    }
}
