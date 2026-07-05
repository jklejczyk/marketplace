<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $table = 'products';
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'vendor_id',
        'vendor_name',
        'variants',
        'tags',
        'attributes',
        'category_id',
        'category_path',
        'avg_rating',
        'reviews_count',
        'active',
    ];


    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'vendor_id' => ObjectId::class,
            'category_id' => ObjectId::class,
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }


}
