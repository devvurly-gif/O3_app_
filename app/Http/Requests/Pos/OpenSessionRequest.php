<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class OpenSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pos_terminal_id' => ['required', 'integer', 'exists:pos_terminals,id'],
            'opening_cash'    => ['required', 'numeric', 'min:0'],
        ];
    }
}
