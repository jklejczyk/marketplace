<?php

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
        Schema::connection('mongodb')->table('reviews', function (Blueprint $collection) {
            $collection->index(['product_id' => 1], 'review_product_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->table('reviews', function (Blueprint $collection) {
            $collection->dropIndex('review_product_idx');
        });
    }
};
