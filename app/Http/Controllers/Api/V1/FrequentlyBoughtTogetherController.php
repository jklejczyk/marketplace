<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\FrequentlyBoughtTogether;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListLimitRequest;
use App\Http\Resources\Api\V1\FrequentlyBoughtTogetherResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FrequentlyBoughtTogetherController extends Controller
{
    public function __invoke(Product $product, ListLimitRequest $request, FrequentlyBoughtTogether $action): AnonymousResourceCollection
    {
        return FrequentlyBoughtTogetherResource::collection($action->handle($product, $request->limit()));
    }
}
