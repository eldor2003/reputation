<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Brand24IngestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_uuid' => ['required', 'uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'mention_id' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_id' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ];
    }
}
