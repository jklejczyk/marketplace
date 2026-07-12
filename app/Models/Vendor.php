<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'vendors';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'description',
        'rating',
        'products_count',
        'active',
        'location',
    ];
}
