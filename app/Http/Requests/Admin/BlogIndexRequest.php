<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(BlogStatus::class)],
            // "none" = blogs without a category / author
            'categoryId' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'],
            'authorId' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['updated', 'newest', 'oldest', 'title', 'views'])],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
