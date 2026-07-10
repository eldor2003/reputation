<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class YouScanIngestRequest extends FormRequest
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
            'id' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'author' => ['nullable'],
            'published' => ['nullable', 'date'],
        ];
    }
}
