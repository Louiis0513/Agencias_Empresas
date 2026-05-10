<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualAccountPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'creditor_name' => ['required', 'string', 'max:255'],
            'creditor_document' => ['nullable', 'string', 'max:64'],
            'document_reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'total_amount' => ['required', 'string', 'max:32'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'creditor_name.required' => 'Indica el nombre del acreedor (quien presentó la cuenta de cobro o documento).',
        ];
    }
}
