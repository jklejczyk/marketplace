<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SearchProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
