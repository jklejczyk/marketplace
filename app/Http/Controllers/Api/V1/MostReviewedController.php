<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\MostReviewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListLimitRequest;
use App\Http\Resources\Api\V1\MostReviewedResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MostReviewedController extends Controller
{
    public function __invoke(ListLimitRequest $request, MostReviewed $action): AnonymousResourceCollection
    {
        return MostReviewedResource::collection($action->handle(limit: $request->limit()));
    }
}
