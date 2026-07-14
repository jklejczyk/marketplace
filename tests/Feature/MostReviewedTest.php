<?php

use App\Actions\Products\MostReviewed;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;

beforeEach(function () {
    Product::truncate();
    Review::truncate();
    User::factory()->count(3)->create();
});

function reviewsFor(Product $product, array $ratings): void
{
    foreach ($ratings as $rating) {
        Review::factory()->forProduct($product)->create(['rating' => $rating]);
    }
}

test('liczy recenzje i średni rating per produkt, sortuje malejąco po liczbie recenzji', function () {
    $a = Product::factory()->create(['name' => 'AAA']);
    $b = Product::factory()->create(['name' => 'BBB']);
    $c = Product::factory()->create(['name' => 'CCC']);

    reviewsFor($a, [5, 4, 3]);
    reviewsFor($b, [2, 2]);
    reviewsFor($c, [5]);

    $result = (new MostReviewed)->handle();

    expect($result)->toHaveCount(3);

    $byProduct = collect($result)->keyBy(fn (array $row): string => (string) $row['_id']);

    expect($byProduct[$a->id]['reviews_count'])->toBe(3)
        ->and($byProduct[$b->id]['reviews_count'])->toBe(2)
        ->and($byProduct[$c->id]['reviews_count'])->toBe(1);

    expect($byProduct[$a->id]['avg_rating'])->toEqualWithDelta(4.0, 0.001)
        ->and($byProduct[$b->id]['avg_rating'])->toEqualWithDelta(2.0, 0.001)
        ->and($byProduct[$c->id]['avg_rating'])->toEqualWithDelta(5.0, 0.001);

    expect((string) $result[0]['_id'])->toBe((string) $a->id);
});

test('produkt bez recenzji nie pojawia się w wyniku', function () {
    $reviewed = Product::factory()->create(['name' => 'Reviewed']);
    Product::factory()->create(['name' => 'Ignored']); // zero recenzji

    reviewsFor($reviewed, [4]);

    $result = (new MostReviewed)->handle();

    expect($result)->toHaveCount(1)
        ->and((string) $result[0]['_id'])->toBe((string) $reviewed->id);
});
