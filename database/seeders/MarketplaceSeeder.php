<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    private const VENDORS = 100;

    private const PRODUCTS = 10000;

    private const USERS = 200;

    private const ORDERS = 3000;

    private const REVIEWED_PRODUCTS = 800;

    private const MAX_REVIEWS_PER_PRODUCT = 25;

    private const CARTS = 10;

    public function run(): void
    {
        $this->command->warn('Czyszczenie kolekcji Mongo...');
        Product::truncate();
        Vendor::truncate();
        Category::truncate();
        Order::truncate();
        Review::truncate();
        Cart::truncate();

        $leaves = $this->seedCategoryTree();
        $this->command->info('Kategorie: '.Category::count().' (w tym liści: '.count($leaves).')');

        $vendors = Vendor::factory()->count(self::VENDORS)->create();
        $this->command->info('Vendorzy: '.$vendors->count());

        $this->seedProducts($vendors, $leaves);
        $this->command->info('Produkty: '.Product::count());

        $this->backfillVendorProductCounts();
        $this->command->info('Backfill products_count przez $group: gotowe');

        $users = $this->seedUsers();
        $this->command->info('Userzy (sqlite): '.$users->count());

        $this->seedOrders();
        $this->command->info('Zamówienia: '.Order::count());

        $this->seedReviews($users);
        $this->command->info('Recenzje: '.Review::count());

        $this->backfillProductRatings();
        $this->command->info('Backfill avg_rating/reviews_count przez $group: gotowe');

        $this->seedCarts($users);
        $this->command->info('Koszyki: '.Cart::count());
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

    private function seedUsers(): Collection
    {
        return User::factory()->count(self::USERS)->create();
    }

    private function seedOrders(): void
    {
        for ($i = 1; $i <= self::ORDERS; $i++) {
            Order::factory()->create();

            if ($i % 500 === 0) {
                $this->command->info("  zamówienia: {$i}/".self::ORDERS);
            }
        }
    }

    private function seedReviews(Collection $users): void
    {
        $products = Product::all(['_id'])->random(min(self::REVIEWED_PRODUCTS, Product::count()));
        $done = 0;

        foreach ($products as $product) {
            $count = fake()->numberBetween(0, self::MAX_REVIEWS_PER_PRODUCT);

            for ($j = 0; $j < $count; $j++) {
                Review::factory()
                    ->forProduct($product)
                    ->forUser($users->random())
                    ->create();
            }

            if (++$done % 100 === 0) {
                $this->command->info("  produkty z recenzjami: {$done}/".$products->count());
            }
        }
    }

    private function seedCarts(Collection $users): void
    {
        $buyers = $users->random(min(self::CARTS, $users->count()));

        foreach ($buyers as $buyer) {
            Cart::factory()->forUser($buyer)->create();
        }
    }

    private function backfillProductRatings(): void
    {
        $stats = DB::connection('mongodb')
            ->getDatabase()
            ->selectCollection('reviews')
            ->aggregate([
                ['$group' => [
                    '_id' => '$product_id',
                    'avg_rating' => ['$avg' => '$rating'],
                    'reviews_count' => ['$sum' => 1],
                ]],
            ]);

        foreach ($stats as $row) {
            Product::where('_id', $row['_id'])->update([
                'avg_rating' => round((float) $row['avg_rating'], 2),
                'reviews_count' => $row['reviews_count'],
            ]);
        }
    }
}
