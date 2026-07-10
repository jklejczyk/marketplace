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
            ['name' => 'text', 'description' => 'text'],
            [
                'name' => 'search_idx',
                'weights' => ['name' => 10, 'description' => 2],
                'default_language' => 'none',
            ]
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->table('products', function (Blueprint $collection) {
            $collection->dropIndex('search_idx');
        });
    }
};
