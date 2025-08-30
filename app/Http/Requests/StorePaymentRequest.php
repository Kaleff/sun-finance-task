<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePaymentRequest extends FormRequest
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
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            // Date format 2022-12-12T15:19:21+00:00
            'paymentDate' => 'required|date_format:Y-m-d\TH:i:sP',
            'amount' => 'required|numeric|min:0.01',
            'refId' => 'required|string|unique:payments,payment_reference',
            'description' => 'required|string|exists:loans,reference',
        ];
    }

    /**
     * Normalize inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        // Extract loan reference from description, stripping by LN + all digits afterward
        $raw = $this->input('description');
        if (is_string($raw) && $raw !== '') {
            if (preg_match('/LN(\d{8,})/i', $raw, $m)) {
                $normalized = strtoupper($m[0]); // "LN" + all digits (min 8)
                $this->merge([
                    'description' => $normalized,
                ]);
            }
        }
    }

    /**
     * Return custom JSON response on validation failure.
     */
    protected function failedValidation(Validator $validator)
    {
        $is_duplicate = (isset($validator->failed()['refId']) && array_key_exists('Unique', $validator->failed()['refId']));

        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
            'success' => false,
            'message' => $is_duplicate ? 'Duplicate payment reference.' : 'Validation failed.',
        ], $is_duplicate ? 409 : 400));
    }
}
