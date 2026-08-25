<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class ValidateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Le caractère obligatoire dépend de l'écart constaté, que seul le
            // service connaît : il refuse la validation sans procès-verbal
            // quand la caisse ne tombe pas juste.
            'variance_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
