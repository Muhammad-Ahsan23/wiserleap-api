<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Rules\UniqueName;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'min:2', 'max:40', new UniqueName('categories', 'category', $category?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter a category name (at least 2 characters).',
            'name.min' => 'Enter a category name (at least 2 characters).',
            'name.max' => 'Keep the category name under 40 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name')))]);
    }
}
