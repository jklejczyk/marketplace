<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VendorsNearbyRequest extends FormRequest
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
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'radius' => ['sometimes', 'integer', 'min:1', 'max:50000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
