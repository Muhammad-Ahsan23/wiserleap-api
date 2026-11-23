<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InquiryReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => is_string($this->input('subject')) ? trim(preg_replace('/[\p{C}]+/u', ' ', $this->input('subject'))) ?: null : $this->input('subject'),
            'message' => is_string($this->input('message')) ? trim(str_replace("\r\n", "\n", $this->input('message'))) : $this->input('message'),
        ]);
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:2', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return ['message.required' => 'Write your reply first.'];
    }
}
