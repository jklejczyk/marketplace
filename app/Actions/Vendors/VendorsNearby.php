<?php

namespace App\Actions\Vendors;

use App\Models\Vendor;

class VendorsNearby
{
    private const LIMIT = 10;

    private const MAX_LIMIT = 100;

    public function handle(float $lng, float $lat, int $radiusMeters = 5000, int $limit = self::LIMIT): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $pipeline = [
            ['$geoNear' => ['near' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'distanceField' => 'distance_m',
                'maxDistance' => $radiusMeters,
                'spherical' => true,
            ],
            ],
            ['$limit' => $limit],
        ];

        $result = Vendor::raw(fn ($collection) => $collection->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray());

        return $result;
    }
}
