<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value, bool $multiline = false) => is_string($value)
            ? trim(preg_replace($multiline ? '/[^\P{C}\n\t]/u' : '/[\p{C}]+/u', $multiline ? '' : ' ', str_replace("\r\n", "\n", $value)))
            : $value;

        $this->merge([
            'name' => $clean($this->input('name')),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'subject' => $clean($this->input('subject')) ?: null,
            'budget' => $clean($this->input('budget')) ?: null,
            'message' => $clean($this->input('message'), true),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'max:200', 'email:rfc'],
            'subject' => ['nullable', 'string', 'max:150'],
            'budget' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'message.required' => 'Please enter your message.',
            'message.min' => 'Please write a little more (at least 10 characters).',
        ];
    }
}
