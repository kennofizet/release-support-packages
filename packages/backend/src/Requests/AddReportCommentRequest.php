<?php declare(strict_types=1);

namespace Kennofizet\ReleaseSupport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddReportCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
