<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mirrors StorePurchaseRequest for the header/financial fields, but adapts the
     * line-item block to the edit payload structure that edit_purchase.blade.php
     * actually sends: 'items' with 'quantity' and 'purchase_price' (not 'cart' with
     * 'qty'/'price'). Rules are intentionally no stricter than store()'s so no
     * currently-valid update request is rejected.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|integer|exists:db_warehouse,id',
            'supplier_id' => 'required|integer|exists:db_suppliers,id',
            'purchase_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:1000',

            // Financial inputs (can be nullable, but numeric if present)
            'discount_on_all' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:Fixed,Percentage,fixed,percentage',
            'other_charges_input' => 'nullable|numeric|min:0',
            'other_charges_tax_id' => 'nullable|integer|exists:db_tax,id',
            'round_off' => 'nullable|numeric', // Can be negative for round down
            'amount_paid' => 'nullable|numeric|min:0',
            'payment_type' => 'nullable|string|max:100',
            'account_id' => [
                'nullable',
                'integer',
                'exists:ac_accounts,id',
                \Illuminate\Validation\Rule::requiredIf(fn () => (float) request('amount_paid', 0) > 0),
            ],

            // Line items — adapted to update()'s payload (items/quantity/purchase_price)
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:db_items,id|distinct',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:Fixed,Percentage,fixed,percentage',
            'items.*.tax_id' => 'nullable|integer|exists:db_tax,id',
            'items.*.tax_type' => 'nullable|in:Inclusive,Exclusive,inclusive,exclusive',
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'account_id.required' => 'Please select a Bank / Cash Account when entering a paid amount.',
            'items.required' => 'Please add at least one item to the purchase.',
            'items.*.item_id.exists' => 'One or more items in your purchase do not exist.',
            'items.*.item_id.distinct' => 'Each item can only appear once in a purchase.',
            'items.*.quantity.min' => 'Quantity must be greater than zero.',
            'items.*.purchase_price.required' => 'Each line requires a purchase price.',
            'items.*.tax_type.in' => 'Tax type must be Inclusive or Exclusive.',
        ];
    }
}
