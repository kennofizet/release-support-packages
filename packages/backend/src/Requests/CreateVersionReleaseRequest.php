<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVersionReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_ids' => ['required', 'array', 'min:1', 'max:500'],
            'report_ids.*' => ['integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:50000'],
            'is_force' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array', 'max:8'],
        ];
    }

    /** @return list<int> */
    public function reportIds(): array
    {
        $raw = $this->input('report_ids', []);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $raw),
            static fn (int $id) => $id > 0,
        )));
    }
}
