<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Vendors\VendorsInArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VendorsInAreaRequest;
use App\Http\Resources\Api\V1\VendorInAreaResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorsInAreaController extends Controller
{
    public function __invoke(VendorsInAreaRequest $request, VendorsInArea $action): AnonymousResourceCollection
    {
        $validated = $request->validated();

        // Walidacja "numeric" nie rzutuje — współrzędne muszą trafić do GeoJSON
        // jako liczby (float), inaczej Mongo odrzuci punkt ze stringami.
        $ring = array_map(
            fn (array $point): array => [(float) $point[0], (float) $point[1]],
            $validated['ring'],
        );

        return VendorInAreaResource::collection($action->handle(
            $ring,
            (int) ($validated['limit'] ?? 50),
        ));
    }
}
