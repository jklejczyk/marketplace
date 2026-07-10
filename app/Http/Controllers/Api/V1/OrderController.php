<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        $order = app(PlaceOrder::class)->handle($request->buyer(), $request->requestedItems());

        return OrderResource::make($order)->response()->setStatusCode(201);
    }
}
