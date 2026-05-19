<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kennofizet\ReleaseSupport\Models\ReleaseSupportReport;

class ListReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    ReleaseSupportReport::STATUS_OPEN,
                    ReleaseSupportReport::STATUS_IN_PROGRESS,
                    ReleaseSupportReport::STATUS_RESOLVED,
                    ReleaseSupportReport::STATUS_CLOSED,
                ]),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function perPage(): int
    {
        $value = $this->input('per_page', $this->input('perPage', 20));
        $perPage = (int) $value;

        return $perPage > 0 ? $perPage : 20;
    }
}
