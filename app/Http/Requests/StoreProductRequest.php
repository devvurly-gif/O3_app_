<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Product|null $product */
        $product = $this->route('product');
        $unique = $product
            ? 'unique:products,p_sku,' . $product->id
            : 'unique:products,p_sku';

        return [
            'p_title'       => ['required', 'string', 'max:255'],
            'p_sku'         => ['nullable', 'string', 'max:100', $unique],
            'p_ean13'       => ['nullable', 'string', 'max:13'],
            'p_code'        => ['nullable', 'string', 'max:100'],
            'p_description' => ['nullable', 'string'],
            'category_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'      => ['nullable', 'integer', 'exists:brands,id'],
            'p_buy_ht'      => ['nullable', 'numeric', 'min:0'],
            'p_sell_ht'     => ['nullable', 'numeric', 'min:0'],
            'p_sell_ttc'    => ['nullable', 'numeric', 'min:0'],
            'p_taux_tva'    => ['nullable', 'numeric', 'min:0'],
            'p_unit'        => ['nullable', 'string', 'max:50'],
            'p_type'        => ['nullable', 'string', 'in:product,service'],
            'p_status'      => ['nullable', 'boolean'],
        ];
    }
}
