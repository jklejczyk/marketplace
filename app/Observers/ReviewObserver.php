<?php

namespace App\Observers;

use App\Actions\Products\MostReviewed;
use App\Models\Product;
use App\Models\Review;

class ReviewObserver
{
    public function created(Review $review): void
    {
        $this->recompute($review->product_id);
    }

    public function updated(Review $review): void
    {
        if (! $review->wasChanged('rating')) {
            return;
        }

        $this->recompute($review->product_id);
    }

    public function deleted(Review $review): void
    {
        $this->recompute($review->product_id);
    }

    private function recompute(string $productId): void
    {
        $result = (new MostReviewed)->handle($productId);

        if ($result === []) {
            Product::where('id', $productId)->update(['reviews_count' => 0, 'avg_rating' => 0]);
        } else {
            Product::where('id', $productId)->update(['reviews_count' => $result[0]['reviews_count'], 'avg_rating' => $result[0]['avg_rating']]);
        }
    }
}
