<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\ProductSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchProductsRequest;
use App\Http\Resources\Api\V1\ProductSearchResultResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductSearchController extends Controller
{
    public function __invoke(SearchProductsRequest $request, ProductSearch $action): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return ProductSearchResultResource::collection($action->handle(
            $validated['q'],
            (int) ($validated['limit'] ?? 20),
            (bool) ($validated['active'] ?? false)
        ));
    }
}
