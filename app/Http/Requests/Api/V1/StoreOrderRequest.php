<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\RequestedItemData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.sku' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function buyer(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    /**
     * @return list<RequestedItemData>
     */
    public function requestedItems(): array
    {
        return array_values(array_map(
            RequestedItemData::fromArray(...),
            $this->validated()['items'],
        ));
    }
}
