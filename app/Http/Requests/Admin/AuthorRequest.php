<?php

namespace App\Http\Requests\Admin;

use App\Models\Author;
use App\Rules\UniqueName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Create / update an author (multipart; updates use POST with `_method=PUT`). */
class AuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Author|null $author */
        $author = $this->route('author');

        return [
            'name' => ['required', 'string', 'min:2', 'max:60', new UniqueName('authors', 'author', $author?->id)],
            'bio' => ['nullable', 'string', 'max:200'],
            'image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(config('media.author_image_max_kb'))->dimensions(Rule::dimensions()->maxWidth(3000)->maxHeight(3000))],
            'removeImage' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the author\'s name (at least 2 characters).',
            'name.min' => 'Enter the author\'s name (at least 2 characters).',
            'name.max' => 'Keep the name under 60 characters.',
            'bio.max' => 'Keep the bio under 200 characters.',
            'image.max' => 'Use a photo under '.config('media.author_image_max_kb').' KB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name')))]);
    }
}
