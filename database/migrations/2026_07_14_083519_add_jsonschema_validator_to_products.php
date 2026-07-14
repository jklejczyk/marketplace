<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('products', function (Blueprint $collection) {
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['name', 'slug', 'price', 'variants', 'active'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 3, 'maxLength' => 200],
                    'slug' => ['bsonType' => 'string'],
                    'description' => ['bsonType' => 'string'],
                    'price' => ['bsonType' => 'decimal', 'minimum' => 0, 'exclusiveMinimum' => true],
                    'vendor_id' => ['bsonType' => ['objectId', 'null']],
                    'vendor_name' => ['bsonType' => ['string', 'null']],
                    'category_id' => ['bsonType' => ['objectId', 'null']],
                    'category_path' => ['bsonType' => 'array', 'items' => ['bsonType' => 'string']],
                    'variants' => [
                        'bsonType' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['sku', 'stock', 'price'],
                            'properties' => [
                                'sku' => ['bsonType' => 'string'],
                                'size' => ['bsonType' => 'string'],
                                'color' => ['bsonType' => 'string'],
                                'stock' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                                'price' => ['bsonType' => 'decimal', 'minimum' => 0, 'exclusiveMinimum' => true],
                            ],
                        ],
                    ],
                    'total_stock' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                    'tags' => ['bsonType' => 'array', 'items' => ['bsonType' => 'string']],
                    'attributes' => ['bsonType' => 'object'],
                    'avg_rating' => ['bsonType' => ['int', 'long', 'double'], 'minimum' => 0, 'maximum' => 5],
                    'reviews_count' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                    'active' => ['bsonType' => 'bool'],
                ],
            ], validationLevel: 'strict', validationAction: 'error');
        });
    }

    public function down(): void
    {
        $connection = DB::connection('mongodb');

        if ($connection instanceof Connection) {
            $connection->getDatabase()->modifyCollection('products', ['validator' => new stdClass]);
        }
    }
};
