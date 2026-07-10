<?php

use App\Models\Product;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    Product::truncate();
    Review::truncate();
    User::factory()->count(3)->create();
});

/**
 * @param  array<int, int>  $ratings
 */
function addReviews(Product $product, array $ratings): void
{
    foreach ($ratings as $rating) {
        Review::factory()->forProduct($product)->create(['rating' => $rating]);
    }
}

test('created przelicza reviews_count i avg_rating na produkcie', function () {
    $product = Product::factory()->create();

    Review::factory()->forProduct($product)->create(['rating' => 4]);

    $product->refresh();

    expect($product->reviews_count)->toBe(1)
        ->and($product->avg_rating)->toEqualWithDelta(4.0, 0.001);
});

test('kolejne recenzje aktualizują średnią', function () {
    $product = Product::factory()->create();

    addReviews($product, [5, 4, 3]);

    $product->refresh();

    expect($product->reviews_count)->toBe(3)
        ->and($product->avg_rating)->toEqualWithDelta(4.0, 0.001);
});

test('zmiana ratingu recenzji przelicza średnią', function () {
    $product = Product::factory()->create();
    $review = Review::factory()->forProduct($product)->create(['rating' => 5]);

    $product->refresh();
    expect($product->avg_rating)->toEqualWithDelta(5.0, 0.001);

    $review->update(['rating' => 1]);

    $product->refresh();
    expect($product->reviews_count)->toBe(1)
        ->and($product->avg_rating)->toEqualWithDelta(1.0, 0.001);
});

test('usunięcie jednej z wielu recenzji przelicza do pozostałych', function () {
    $product = Product::factory()->create();
    addReviews($product, [5, 5]);
    $toDelete = Review::factory()->forProduct($product)->create(['rating' => 2]);

    $product->refresh();
    expect($product->reviews_count)->toBe(3)
        ->and($product->avg_rating)->toEqualWithDelta(4.0, 0.001);

    $toDelete->delete();

    $product->refresh();
    expect($product->reviews_count)->toBe(2)
        ->and($product->avg_rating)->toEqualWithDelta(5.0, 0.001);
});

test('usunięcie ostatniej recenzji zeruje avg_rating i reviews_count', function () {
    $product = Product::factory()->create();
    $review = Review::factory()->forProduct($product)->create(['rating' => 4]);

    $product->refresh();
    expect($product->reviews_count)->toBe(1);

    $review->delete();

    $product->refresh();
    expect($product->reviews_count)->toBe(0)
        ->and((float) $product->avg_rating)->toBe(0.0);
});
