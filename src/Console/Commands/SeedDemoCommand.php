<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Console\Commands;

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductOption;
use Aliziodev\ProductCatalog\Models\ProductOptionValue;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoCommand extends Command
{
    protected $signature = 'catalog:seed-demo
                            {--force : Skip the production environment guard}';

    protected $description = 'Seed demo products, brands, categories, and tags';

    public function handle(): int
    {
        $env = config('app.env', 'production');
        if (! in_array($env, ['local', 'testing']) && ! $this->option('force')) {
            $this->components->error(
                'catalog:seed-demo should only run in local or testing environments. Use --force to override.'
            );

            return self::FAILURE;
        }

        $this->components->info('Seeding demo catalog data...');

        DB::transaction(function () {
            [$electronics, $smartphones, $laptops, $apparel, $tshirts] = $this->seedCategories();
            [$techco, $stylehouse] = $this->seedBrands();
            [$newArrival, $bestseller, $featured, $sale] = $this->seedTags();

            $this->seedSmartphone($techco, $electronics, $smartphones, $newArrival, $bestseller);
            $this->seedLaptop($techco, $electronics, $laptops, $featured);
            $this->seedTShirt($stylehouse, $apparel, $tshirts, $sale);
            $this->seedDigitalProduct($featured);
        });

        $this->newLine();
        $this->components->info('Demo data seeded successfully.');
        $this->line('  4 products · 2 brands · 5 categories · 4 tags');
        $this->newLine();

        return self::SUCCESS;
    }

    private function seedCategories(): array
    {
        $electronics = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'position' => 1]);
        $smartphones = Category::create(['parent_id' => $electronics->id, 'name' => 'Smartphones', 'slug' => 'smartphones', 'position' => 1]);
        $laptops = Category::create(['parent_id' => $electronics->id, 'name' => 'Laptops', 'slug' => 'laptops', 'position' => 2]);
        $apparel = Category::create(['name' => 'Apparel', 'slug' => 'apparel', 'position' => 2]);
        $tshirts = Category::create(['parent_id' => $apparel->id, 'name' => 'T-Shirts', 'slug' => 't-shirts', 'position' => 1]);

        return [$electronics, $smartphones, $laptops, $apparel, $tshirts];
    }

    private function seedBrands(): array
    {
        $techco = Brand::create([
            'name' => 'TechCo',
            'slug' => 'techco',
            'description' => 'Premium consumer electronics and accessories.',
            'website_url' => 'https://techco.example.com',
        ]);

        $stylehouse = Brand::create([
            'name' => 'StyleHouse',
            'slug' => 'stylehouse',
            'description' => 'Modern everyday apparel.',
            'website_url' => 'https://stylehouse.example.com',
        ]);

        return [$techco, $stylehouse];
    }

    private function seedTags(): array
    {
        $newArrival = Tag::create(['name' => 'New Arrival', 'slug' => 'new-arrival']);
        $bestseller = Tag::create(['name' => 'Bestseller', 'slug' => 'bestseller']);
        $featured = Tag::create(['name' => 'Featured', 'slug' => 'featured']);
        $sale = Tag::create(['name' => 'Sale', 'slug' => 'sale']);

        return [$newArrival, $bestseller, $featured, $sale];
    }

    private function seedSmartphone(Brand $brand, Category $electronics, Category $smartphones, Tag $newArrival, Tag $bestseller): void
    {
        $product = Product::create([
            'brand_id' => $brand->id,
            'primary_category_id' => $smartphones->id,
            'name' => 'Smartphone Pro X',
            'slug' => 'smartphone-pro-x',
            'description' => 'The latest flagship smartphone with a 6.7" OLED display, 5G connectivity, and a 50MP triple camera system.',
            'short_description' => 'Flagship 5G smartphone with 6.7" OLED display.',
            'type' => ProductType::Variable,
            'status' => ProductStatus::Published,
            'featured_image_path' => 'https://placehold.co/800x600?text=Smartphone+Pro+X',
            'meta_title' => 'Smartphone Pro X | TechCo',
            'meta_description' => 'Buy the TechCo Smartphone Pro X — available in Midnight and Silver.',
            'published_at' => now()->subDays(10),
        ]);

        $product->categories()->attach([$electronics->id, $smartphones->id]);
        $product->tags()->attach([$newArrival->id, $bestseller->id]);

        $colorOption = ProductOption::create(['product_id' => $product->id, 'name' => 'Color', 'position' => 1]);
        $storageOption = ProductOption::create(['product_id' => $product->id, 'name' => 'Storage', 'position' => 2]);

        $midnight = ProductOptionValue::create(['option_id' => $colorOption->id, 'value' => 'Midnight', 'position' => 1]);
        $silver = ProductOptionValue::create(['option_id' => $colorOption->id, 'value' => 'Silver', 'position' => 2]);
        $gb128 = ProductOptionValue::create(['option_id' => $storageOption->id, 'value' => '128GB', 'position' => 1]);
        $gb256 = ProductOptionValue::create(['option_id' => $storageOption->id, 'value' => '256GB', 'position' => 2]);

        $variants = [
            ['sku' => 'SPX-MID-128', 'price' => 899.00, 'is_default' => true, 'position' => 1, 'values' => [$midnight->id, $gb128->id]],
            ['sku' => 'SPX-MID-256', 'price' => 999.00, 'is_default' => false, 'position' => 2, 'values' => [$midnight->id, $gb256->id]],
            ['sku' => 'SPX-SIL-128', 'price' => 899.00, 'is_default' => false, 'position' => 3, 'values' => [$silver->id, $gb128->id]],
            ['sku' => 'SPX-SIL-256', 'price' => 999.00, 'is_default' => false, 'position' => 4, 'values' => [$silver->id, $gb256->id]],
        ];

        foreach ($variants as $data) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $data['sku'],
                'price' => $data['price'],
                'is_default' => $data['is_default'],
                'is_active' => true,
                'position' => $data['position'],
            ]);
            $variant->optionValues()->attach($data['values']);
            InventoryItem::create(['variant_id' => $variant->id, 'quantity' => 50, 'policy' => InventoryPolicy::Track]);
        }
    }

    private function seedLaptop(Brand $brand, Category $electronics, Category $laptops, Tag $featured): void
    {
        $product = Product::create([
            'brand_id' => $brand->id,
            'primary_category_id' => $laptops->id,
            'name' => 'Laptop Air 15',
            'slug' => 'laptop-air-15',
            'description' => 'Ultralight 15" laptop with all-day battery life and a stunning Retina display.',
            'short_description' => 'Ultralight 15" laptop, 18-hour battery.',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Published,
            'featured_image_path' => 'https://placehold.co/800x600?text=Laptop+Air+15',
            'meta_title' => 'Laptop Air 15 | TechCo',
            'published_at' => now()->subDays(30),
        ]);

        $product->categories()->attach([$electronics->id, $laptops->id]);
        $product->tags()->attach([$featured->id]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'LAP-AIR15',
            'price' => 1299.00,
            'compare_price' => 1499.00,
            'is_default' => true,
            'is_active' => true,
            'position' => 1,
        ]);
        InventoryItem::create(['variant_id' => $variant->id, 'quantity' => 20, 'policy' => InventoryPolicy::Track]);
    }

    private function seedTShirt(Brand $brand, Category $apparel, Category $tshirts, Tag $sale): void
    {
        $product = Product::create([
            'brand_id' => $brand->id,
            'primary_category_id' => $tshirts->id,
            'name' => 'Classic Crew Tee',
            'slug' => 'classic-crew-tee',
            'description' => '100% organic cotton crew-neck t-shirt, pre-washed for softness.',
            'short_description' => 'Organic cotton crew tee in 3 colors and 3 sizes.',
            'type' => ProductType::Variable,
            'status' => ProductStatus::Published,
            'featured_image_path' => 'https://placehold.co/800x600?text=Classic+Crew+Tee',
            'published_at' => now()->subDays(5),
        ]);

        $product->categories()->attach([$apparel->id, $tshirts->id]);
        $product->tags()->attach([$sale->id]);

        $colorOption = ProductOption::create(['product_id' => $product->id, 'name' => 'Color', 'position' => 1]);
        $sizeOption = ProductOption::create(['product_id' => $product->id, 'name' => 'Size', 'position' => 2]);

        $colors = [
            ProductOptionValue::create(['option_id' => $colorOption->id, 'value' => 'White', 'position' => 1]),
            ProductOptionValue::create(['option_id' => $colorOption->id, 'value' => 'Black', 'position' => 2]),
            ProductOptionValue::create(['option_id' => $colorOption->id, 'value' => 'Navy', 'position' => 3]),
        ];
        $sizes = [
            ProductOptionValue::create(['option_id' => $sizeOption->id, 'value' => 'S', 'position' => 1]),
            ProductOptionValue::create(['option_id' => $sizeOption->id, 'value' => 'M', 'position' => 2]),
            ProductOptionValue::create(['option_id' => $sizeOption->id, 'value' => 'L', 'position' => 3]),
        ];

        $position = 1;
        foreach ($colors as $i => $color) {
            foreach ($sizes as $j => $size) {
                $isDefault = $i === 1 && $j === 1; // Black / M
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => 'CCT-'.strtoupper(substr($color->value, 0, 3)).'-'.$size->value,
                    'price' => 29.00,
                    'compare_price' => 39.00,
                    'is_default' => $isDefault,
                    'is_active' => true,
                    'position' => $position++,
                ]);
                $variant->optionValues()->attach([$color->id, $size->id]);
                InventoryItem::create(['variant_id' => $variant->id, 'quantity' => 100, 'policy' => InventoryPolicy::Track]);
            }
        }
    }

    private function seedDigitalProduct(Tag $featured): void
    {
        $product = Product::create([
            'name' => 'Premium License Key',
            'slug' => 'premium-license-key',
            'description' => 'Lifetime license key for our premium desktop application.',
            'short_description' => 'One-time purchase, lifetime access.',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Published,
            'published_at' => now()->subDays(60),
        ]);

        $product->tags()->attach([$featured->id]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DIG-LIC-PREM',
            'price' => 49.00,
            'is_default' => true,
            'is_active' => true,
            'position' => 1,
        ]);

        // Digital product — always in stock
        InventoryItem::create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);
    }
}
