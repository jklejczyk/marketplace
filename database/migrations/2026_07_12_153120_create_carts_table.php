<?php

use App\Models\Cart;
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
        Cart::raw(fn ($col) => $col->createIndex(
            ['updated_at' => 1],
            ['name' => 'cart_ttl_idx', 'expireAfterSeconds' => 604800],
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->table('carts', function (Blueprint $collection) {
            $collection->dropIndex('cart_ttl_idx');
        });
    }
};
