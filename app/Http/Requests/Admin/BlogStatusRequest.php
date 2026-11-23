<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlogStatus;
use App\Models\Blog;
use App\Support\PublishChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BlogStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(BlogStatus::class)]];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('status') !== BlogStatus::Published->value) {
                    return;
                }

                /** @var Blog $blog */
                $blog = $this->route('blog');
                $missing = PublishChecklist::missing([
                    'excerpt' => $blog->excerpt,
                    'content' => $blog->content,
                    'authorId' => $blog->author_id,
                    'categoryId' => $blog->category_id,
                    'hasImage' => (bool) $blog->image_path,
                ]);

                if ($missing) {
                    $validator->errors()->add('status', 'Can\'t publish yet: '.implode(' ', $missing));
                }
            },
        ];
    }
}
