<?php

namespace App\Models;

use App\Observers\ReviewObserver;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

#[ObservedBy(ReviewObserver::class)]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'user_snapshot',
        'rating',
        'title',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => ObjectId::class,
            'rating' => 'integer',
        ];
    }
}
