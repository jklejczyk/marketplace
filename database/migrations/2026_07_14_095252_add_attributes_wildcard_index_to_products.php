<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Product::raw(fn ($c) => $c->createIndex(
            ['attributes.$**' => 1],
            ['name' => 'attributes_wildcard_idx']
        ));
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('products', function (Blueprint $collection) {
            $collection->dropIndex('attributes_wildcard_idx');
        });
    }
};
