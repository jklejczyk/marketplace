<?php

use App\Http\Controllers\Api\V1\FacetedProductSearchController;
use App\Http\Controllers\Api\V1\FrequentlyBoughtTogetherController;
use App\Http\Controllers\Api\V1\MostReviewedController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\TopVendorsController;
use App\Http\Controllers\Api\V1\VendorsInAreaController;
use App\Http\Controllers\Api\V1\VendorsNearbyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('products', FacetedProductSearchController::class);
    Route::get('products/most-reviewed', MostReviewedController::class);
    Route::get('products/{product}/frequently-bought-together', FrequentlyBoughtTogetherController::class);
    Route::get('vendors/top', TopVendorsController::class);
    Route::get('vendors/nearby', VendorsNearbyController::class);
    Route::post('vendors/in-area', VendorsInAreaController::class);
    Route::post('orders', OrderController::class)->middleware('auth:sanctum');
});
