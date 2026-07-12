<?php

namespace App\Actions\Vendors;

use App\Models\Vendor;

class VendorsInArea
{
    private const LIMIT = 50;

    private const MAX_LIMIT = 100;

    public function handle(array $ring, int $limit = self::LIMIT): array
    {
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $filter = [
            'location' => [
                '$geoWithin' => [
                    '$geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [$this->closeRing($ring)],
                    ],
                ],
            ],
        ];

        return Vendor::raw(fn ($collection) => $collection->find($filter, [
            'limit' => $limit, 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ])->toArray());
    }

    private function closeRing(array $ring): array
    {
        if ($ring[0] !== end($ring)) {
            $ring[] = $ring[0];
        }

        return $ring;
    }
}
