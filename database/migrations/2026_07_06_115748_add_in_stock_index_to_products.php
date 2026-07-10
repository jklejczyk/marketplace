<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Product::raw(fn ($c) => $c->createIndex(
            ['category_path' => 1, 'avg_rating' => -1, 'price' => 1],
            [
                'name' => 'in_stock_idx',
                'partialFilterExpression' => ['active' => true, 'total_stock' => ['$gt' => 0]],
            ]
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->table('products', function (Blueprint $collection) {
            $collection->dropIndex('in_stock_idx');
        });
    }
};
