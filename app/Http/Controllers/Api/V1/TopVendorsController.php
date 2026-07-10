<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\TopVendorsByRevenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListLimitRequest;
use App\Http\Resources\Api\V1\TopVendorResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TopVendorsController extends Controller
{
    public function __invoke(ListLimitRequest $request, TopVendorsByRevenue $action): AnonymousResourceCollection
    {
        return TopVendorResource::collection($action->handle($request->limit()));
    }
}
