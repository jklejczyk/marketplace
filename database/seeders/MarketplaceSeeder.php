<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    private const VENDORS = 100;

    private const PRODUCTS = 10000;

    public function run(): void
    {
        $this->command->warn('Czyszczenie kolekcji Mongo...');
        Product::truncate();
        Vendor::truncate();
        Category::truncate();

        $leaves = $this->seedCategoryTree();
        $this->command->info('Kategorie: '.Category::count().' (w tym liści: '.count($leaves).')');

        $vendors = Vendor::factory()->count(self::VENDORS)->create();
        $this->command->info('Vendorzy: '.$vendors->count());

        $this->seedProducts($vendors, $leaves);
        $this->command->info('Produkty: '.Product::count());

        $this->backfillVendorProductCounts();
        $this->command->info('Backfill products_count przez $group: gotowe');
    }

    private function seedCategoryTree(): array
    {
        $taxonomy = [
            'Odzież' => [
                'Bluzy' => ['Z kapturem', 'Bez kaptura'],
                'Koszulki' => ['T-shirty', 'Koszulki polo'],
                'Spodnie' => ['Jeansy', 'Dresowe'],
            ],
            'Elektronika' => [
                'Telefony' => ['Smartfony', 'Akcesoria GSM'],
                'Komputery' => ['Laptopy', 'Podzespoły PC'],
            ],
            'Dom i ogród' => [
                'Kuchnia' => ['Garnki', 'Sztućce'],
                'Meble' => ['Krzesła', 'Stoły'],
            ],
        ];

        $leaves = [];
        $this->buildBranch($taxonomy, null, [], $leaves);

        return $leaves;
    }

    private function buildBranch(array $branch, ?Category $parent, array $path, array &$leaves): void
    {
        foreach ($branch as $key => $value) {
            if (is_int($key)) {
                $node = $this->makeCategory($value, $parent);
                $leaves[] = ['category' => $node, 'path' => [...$path, $value]];

                continue;
            }

            $node = $this->makeCategory($key, $parent);
            $this->buildBranch($value, $node, [...$path, $key], $leaves);
        }
    }

    private function makeCategory(string $name, ?Category $parent): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => $parent ? (string) $parent->id : null,
        ]);
    }

    private function seedProducts(Collection $vendors, array $leaves): void
    {
        for ($i = 1; $i <= self::PRODUCTS; $i++) {
            $leaf = $leaves[array_rand($leaves)];

            Product::factory()
                ->forVendor($vendors->random())
                ->inCategory($leaf['category'], $leaf['path'])
                ->create();

            if ($i % 100 === 0) {
                $this->command->info("  produkty: {$i}/".self::PRODUCTS);
            }
        }
    }

    private function backfillVendorProductCounts(): void
    {
        $counts = DB::connection('mongodb')
            ->getDatabase()
            ->selectCollection('products')
            ->aggregate([
                ['$group' => ['_id' => '$vendor_id', 'count' => ['$sum' => 1]]],
            ]);

        foreach ($counts as $row) {
            Vendor::where('_id', $row['_id'])->update(['products_count' => $row['count']]);
        }
    }
}
