<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentHeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_title'           => ['nullable', 'string', 'max:255'],
            'thirdPartner_id'          => ['nullable', 'integer', 'exists:third_partners,id'],
            'company_role'             => ['nullable', 'string', 'max:50'],
            'warehouse_id'             => ['nullable', 'integer', 'exists:warehouses,id'],
            'warehouse_dest_id'        => ['nullable', 'integer', 'exists:warehouses,id'],
            'status'                   => ['sometimes', 'string', 'max:50'],
            'issued_at'                => ['nullable', 'date'],
            'due_at'                   => ['nullable', 'date'],
            'notes'                    => ['nullable', 'string'],
            // Lines
            'lines'                    => ['sometimes', 'array'],
            'lines.*.designation'      => ['required_with:lines', 'string', 'max:255'],
            'lines.*.product_id'       => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.reference'        => ['nullable', 'string', 'max:100'],
            'lines.*.quantity'         => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit'             => ['nullable', 'string', 'max:50'],
            'lines.*.unit_price'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_percent'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_type'        => ['nullable', 'string', 'max:50'],
            // Footer
            'footer'                   => ['sometimes', 'array'],
            'footer.total_ht'          => ['nullable', 'numeric'],
            'footer.total_discount'    => ['nullable', 'numeric'],
            'footer.total_tax'         => ['nullable', 'numeric'],
            'footer.total_ttc'         => ['nullable', 'numeric'],
            'footer.amount_paid'       => ['nullable', 'numeric'],
            'footer.amount_due'        => ['nullable', 'numeric'],
        ];
    }

    public function headerData(): array
    {
        return collect($this->validated())->except(['lines', 'footer'])->all();
    }

    public function linesData(): ?array
    {
        return $this->has('lines') ? ($this->validated()['lines'] ?? []) : null;
    }

    public function footerData(): ?array
    {
        return $this->has('footer') ? ($this->validated()['footer'] ?? null) : null;
    }
}
