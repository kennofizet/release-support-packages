<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveVersionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:50000'],
            'is_force' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array', 'max:8'],
        ];
    }
}
