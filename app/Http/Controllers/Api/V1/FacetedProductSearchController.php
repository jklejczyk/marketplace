<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\FacetedProductSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductSearchRequest;
use App\Http\Resources\Api\V1\FacetedSearchResource;

class FacetedProductSearchController extends Controller
{
    public function __invoke(ProductSearchRequest $request, FacetedProductSearch $action): FacetedSearchResource
    {
        return new FacetedSearchResource($action->handle($request->filters()));
    }
}
