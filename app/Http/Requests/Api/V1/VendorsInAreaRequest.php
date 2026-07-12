<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VendorsInAreaRequest extends FormRequest
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
            'ring' => ['required', 'array', 'min:3'],
            'ring.*' => ['array', 'size:2'],
            'ring.*.0' => ['numeric', 'between:-180,180'],
            'ring.*.1' => ['numeric', 'between:-90,90'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
