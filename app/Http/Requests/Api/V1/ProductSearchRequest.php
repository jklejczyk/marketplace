<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ProductSearchRequest extends FormRequest
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
            'category' => ['sometimes', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*' => ['string'],
            'price_min' => ['sometimes', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'numeric', 'min:0'],
            'sort' => ['sometimes', 'in:price_asc,price_desc,newest'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Mapuje zwalidowane query params (snake_case) na kształt filtrów,
     * którego oczekuje FacetedProductSearch (camelCase).
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            ...isset($validated['category']) ? ['category' => $validated['category']] : [],
            ...isset($validated['tags']) ? ['tags' => $validated['tags']] : [],
            ...isset($validated['attributes']) ? ['attributes' => $validated['attributes']] : [],
            ...isset($validated['price_min']) ? ['priceMin' => $validated['price_min']] : [],
            ...isset($validated['price_max']) ? ['priceMax' => $validated['price_max']] : [],
            ...isset($validated['sort']) ? ['sort' => $validated['sort']] : [],
            ...isset($validated['page']) ? ['page' => (int) $validated['page']] : [],
            ...isset($validated['per_page']) ? ['perPage' => (int) $validated['per_page']] : [],
        ];
    }
}
