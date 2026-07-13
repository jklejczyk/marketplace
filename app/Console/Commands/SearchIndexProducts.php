<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search:index-products {--fresh : Usuń i utwórz indeks od nowa}')]
#[Description('Tworzy/aktualizuje Atlas Search index products_search (analizator lucene.polish)')]
class SearchIndexProducts extends Command
{
    private const INDEX = 'products_search';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $definition = [
            'mappings' => [
                'dynamic' => false,
                'fields' => [
                    'name' => [
                        [
                            'type' => 'string',
                            'analyzer' => 'lucene.polish',
                            'multi' => [
                                'standard' => ['type' => 'string', 'analyzer' => 'lucene.standard'],
                            ],
                        ],
                        ['type' => 'autocomplete', 'analyzer' => 'lucene.standard'],
                    ],
                    'description' => ['type' => 'string', 'analyzer' => 'lucene.polish'],
                    'active' => ['type' => 'boolean'],
                ],
            ],
        ];

        $exists = $this->indexExists();

        if ($exists && $this->option('fresh')) {
            Product::raw(fn ($collection) => $collection->dropSearchIndex(self::INDEX));
            $exists = false;
        }

        if ($exists) {
            Product::raw(fn ($collection) => $collection->updateSearchIndex(self::INDEX, $definition));
            $this->info('Zaktualizowano definicję indeksu.');
        } else {
            Product::raw(fn ($collection) => $collection->createSearchIndex($definition, ['name' => self::INDEX]));
            $this->info('Utworzono indeks.');
        }

        return $this->waitUntilReady();
    }

    private function indexExists(): bool
    {
        return collect(Product::raw(fn ($collection) => iterator_to_array($collection->listSearchIndexes())))
            ->contains(fn ($index) => $index['name'] === self::INDEX);
    }

    private function waitUntilReady(int $timeoutSeconds = 60): int
    {
        $this->info('Czekam aż mongot zbuduje indeks (async)...');

        for ($elapsed = 0; $elapsed < $timeoutSeconds; $elapsed += 2) {
            $status = collect(Product::raw(fn ($collection) => iterator_to_array($collection->listSearchIndexes())))
                ->firstWhere('name', self::INDEX)['status'] ?? 'UNKNOWN';

            if ($status === 'READY') {
                $this->info('Indeks '.self::INDEX.' gotowy (READY).');

                return self::SUCCESS;
            }

            if ($status === 'FAILED') {
                $this->error('Build indeksu FAILED.');

                return self::FAILURE;
            }

            $this->line("  status: {$status}...");
            sleep(2);
        }

        $this->warn('Timeout — indeks wciąż się buduje, sprawdź później.');

        return self::FAILURE;
    }
}
