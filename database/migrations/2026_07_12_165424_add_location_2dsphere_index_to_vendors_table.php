<?php

use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Vendor::raw(function ($collection) {
            $collection->createIndex(['location' => '2dsphere']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Vendor::raw(fn ($collection) => $collection->dropIndex('location_2dsphere'));
    }
};
