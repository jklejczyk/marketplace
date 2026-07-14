<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('orders', function (Blueprint $collection) {
            $collection->jsonSchema([
                'bsonType' => 'object',
                'required' => ['user_id', 'user_snapshot', 'items', 'total', 'status'],
                'properties' => [
                    'user_id' => ['bsonType' => ['int', 'long']],
                    'user_snapshot' => [
                        'bsonType' => 'object',
                        'required' => ['name', 'email'],
                        'properties' => [
                            'name' => ['bsonType' => 'string'],
                            'email' => ['bsonType' => 'string', 'pattern' => '^.+@.+$'],
                        ],
                    ],
                    'items' => [
                        'bsonType' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['product_id', 'name_snapshot', 'price_snapshot', 'vendor_id', 'quantity'],
                            'properties' => [
                                'product_id' => ['bsonType' => 'objectId'],
                                'name_snapshot' => ['bsonType' => 'string'],
                                'price_snapshot' => ['bsonType' => 'decimal', 'minimum' => 0, 'exclusiveMinimum' => true],
                                'variant_snapshot' => ['bsonType' => 'object'],
                                'vendor_id' => ['bsonType' => 'objectId'],
                                'vendor_name' => ['bsonType' => 'string'],
                                'quantity' => ['bsonType' => ['int', 'long'], 'minimum' => 1],
                            ],
                        ],
                    ],
                    'total' => ['bsonType' => 'decimal', 'minimum' => 0, 'exclusiveMinimum' => true],
                    'status' => ['enum' => OrderStatus::values()],
                ],
            ], validationLevel: 'strict', validationAction: 'error');
        });
    }

    public function down(): void
    {
        $connection = DB::connection('mongodb');

        if ($connection instanceof Connection) {
            $connection->getDatabase()->modifyCollection('orders', ['validator' => new stdClass]);
        }
    }
};
