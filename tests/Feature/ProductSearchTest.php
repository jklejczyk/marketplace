<?php

use App\Actions\Products\ProductSearch;
use App\Models\Product;

function ensureSearchIndex(): void
{
    $definition = [
        'mappings' => [
            'dynamic' => false,
            'fields' => [
                'name' => [
                    [
                        'type' => 'string',
                        'analyzer' => 'lucene.polish',
                        'multi' => ['standard' => ['type' => 'string', 'analyzer' => 'lucene.standard']],
                    ],
                    ['type' => 'autocomplete', 'analyzer' => 'lucene.standard'],
                ],
                'description' => ['type' => 'string', 'analyzer' => 'lucene.polish'],
                'active' => ['type' => 'boolean'],
            ],
        ],
    ];

    $existing = collect(Product::raw(fn ($c) => iterator_to_array($c->listSearchIndexes())))
        ->firstWhere('name', 'products_search');

    if ($existing === null) {
        Product::raw(fn ($c) => $c->createSearchIndex($definition, ['name' => 'products_search']));
    } else {
        $current = json_decode(json_encode($existing), true);

        if (! isset($current['latestDefinition']['mappings']['fields']['active'])) {
            Product::raw(fn ($c) => $c->updateSearchIndex('products_search', $definition));
        }
    }

    for ($i = 0; $i < 120; $i++) {
        $status = collect(Product::raw(fn ($c) => iterator_to_array($c->listSearchIndexes())))
            ->firstWhere('name', 'products_search')['status'] ?? 'UNKNOWN';

        if ($status === 'READY') {
            return;
        }

        usleep(500_000);
    }
}

function waitForSearch(string $query, string $expectedName, int $timeoutSeconds = 20): array
{
    $action = new ProductSearch;

    for ($i = 0; $i < $timeoutSeconds * 2; $i++) {
        $results = $action->handle($query);

        if (collect($results)->contains(fn ($row) => $row['name'] === $expectedName)) {
            return $results;
        }

        usleep(500_000);
    }

    return $action->handle($query);
}

beforeEach(function () {
    Product::truncate();
    ensureSearchIndex();
});

test('$search rankuje trafienie w nazwę wyżej niż w opisie (boost name)', function () {
    Product::factory()->create(['name' => 'Kurtka zimowa', 'description' => 'ciepła odzież']);
    Product::factory()->create(['name' => 'Buty trekkingowe', 'description' => 'kurtka wspomniana tylko w opisie']);

    $results = waitForSearch('kurtka', 'Kurtka zimowa');

    expect($results[0]['name'])->toBe('Kurtka zimowa')
        ->and($results[0]['score'])->toBeGreaterThan($results[1]['score'] ?? 0.0);
});

test('$search toleruje literówkę przez fuzzy na multi:standard', function () {
    Product::factory()->create(['name' => 'Adipisci', 'description' => 'x']);

    $results = waitForSearch('adipiscx', 'Adipisci');   // literówka: i->x na końcu

    expect(collect($results)->pluck('name'))->toContain('Adipisci');
});

test('compound.filter (activeOnly) odsiewa nieaktywne, ale NIE zmienia score aktywnych', function () {
    Product::factory()->create(['name' => 'Kurtka aktywna', 'description' => 'x', 'active' => true]);
    Product::factory()->create(['name' => 'Kurtka nieaktywna', 'description' => 'x', 'active' => false]);

    $all = waitForSearch('kurtka', 'Kurtka nieaktywna');
    expect(collect($all)->pluck('name'))
        ->toContain('Kurtka aktywna')
        ->toContain('Kurtka nieaktywna');

    $filtered = (new ProductSearch)->handle('kurtka', 20, activeOnly: true);
    expect(collect($filtered)->pluck('name'))
        ->toContain('Kurtka aktywna')
        ->not->toContain('Kurtka nieaktywna');

    $scoreNoFilter = collect($all)->firstWhere('name', 'Kurtka aktywna')['score'];
    $scoreFiltered = collect($filtered)->firstWhere('name', 'Kurtka aktywna')['score'];
    expect($scoreFiltered)->toBe($scoreNoFilter);
});
