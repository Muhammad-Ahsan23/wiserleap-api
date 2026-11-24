<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // jpg/png/webp only (no SVG: it can carry scripts).
            'file' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(config('media.content_image_max_kb'))->dimensions(Rule::dimensions()->maxWidth(6000)->maxHeight(6000))],
            'alt' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose an image to upload.',
            'file.image' => 'Upload a JPG, PNG or WebP image.',
            'file.mimes' => 'Upload a JPG, PNG or WebP image.',
            'file.max' => 'Use an image under '.round(config('media.content_image_max_kb') / 1024, 1).' MB.',
            'file.dimensions' => 'Use an image up to 6000 × 6000 pixels.',
        ];
    }
}
