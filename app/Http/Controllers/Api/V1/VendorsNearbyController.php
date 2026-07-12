<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Vendors\VendorsNearby;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VendorsNearbyRequest;
use App\Http\Resources\Api\V1\VendorNearbyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorsNearbyController extends Controller
{
    public function __invoke(VendorsNearbyRequest $request, VendorsNearby $action): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return VendorNearbyResource::collection($action->handle(
            (float) $validated['lng'],
            (float) $validated['lat'],
            (int) ($validated['radius'] ?? 5000),
            (int) ($validated['limit'] ?? 10),
        ));
    }
}
