<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreThirdPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tp_title'          => ['required', 'string', 'max:255'],
            'tp_Role'           => ['required', 'string', 'in:client,fournisseur,les_deux'],
            'tp_email'          => ['nullable', 'email', 'max:255'],
            'tp_phone'          => ['nullable', 'string', 'max:50'],
            'tp_address'        => ['nullable', 'string', 'max:500'],
            'tp_city'           => ['nullable', 'string', 'max:100'],
            'tp_zip'            => ['nullable', 'string', 'max:20'],
            'tp_country'        => ['nullable', 'string', 'max:100'],
            'tp_ice'            => ['nullable', 'string', 'max:50'],
            'tp_if'             => ['nullable', 'string', 'max:50'],
            'tp_rc'             => ['nullable', 'string', 'max:50'],
            'tp_cnss'           => ['nullable', 'string', 'max:50'],
            'tp_status'         => ['nullable', 'boolean'],
            'seuil_credit'      => ['nullable', 'numeric', 'min:0'],
            'encours_actuel'    => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
