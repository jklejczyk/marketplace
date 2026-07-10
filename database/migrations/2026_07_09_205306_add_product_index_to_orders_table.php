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
        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->index(['items.product_id' => 1], 'order_items_product_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->dropIndex('order_items_product_idx');
        });
    }
};
