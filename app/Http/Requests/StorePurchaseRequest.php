<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
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

            // Cart validation
            'cart' => 'required|array|min:1',
            'cart.*.item_id' => 'required|integer|exists:db_items,id|distinct',
            'cart.*.qty' => 'required|numeric|min:0.01', 
            'cart.*.price' => 'required|numeric|min:0',
            'cart.*.discount' => 'nullable|numeric|min:0',
            'cart.*.is_serialized' => 'nullable|in:0,1',
        ];
    }

    public function messages()
    {
        return [
            'account_id.required' => 'Please select a Bank / Cash Account when entering a paid amount.',
            'cart.required' => 'Please add at least one item to the cart.',
            'cart.*.item_id.exists' => 'One or more items in your cart do not exist.',
            'cart.*.qty.min' => 'Quantity must be greater than zero.',
        ];
    }
}
