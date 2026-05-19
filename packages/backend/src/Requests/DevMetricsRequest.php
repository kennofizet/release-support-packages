<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DevMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
