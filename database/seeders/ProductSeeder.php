<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // Get category IDs (make sure CategorySeeder has run first)
        $visitingCardsCategory = Category::where('slug', 'visiting-cards')->first();
        $stationeryCategory = Category::where('slug', 'stationery-letterheads-notebooks')->first();
        $winterWearCategory = Category::where('slug', 'custom-winter-wear')->first();
        $giftsCategory = Category::where('slug', 'mugs-albums-gifts')->first();

        // Sample products data based on your cardData
        $products = [
            // Visiting Cards
            [
                'category_id' => $visitingCardsCategory->id ?? 1,
                'name' => 'Standard Business Cards',
                'description' => 'Professional standard business cards with same day delivery available in Mumbai, Bengaluru & Hyderabad. Perfect for networking and professional branding.',
                'short_description' => 'Professional standard business cards with same day delivery.',
                'price' => 200.00,
                'sale_price' => null,
                'stock_quantity' => 500,
                'featured_image' => 'products/visiting-cards/standard.jpg',
                'rating' => 4.6,
                'reviews_count' => 891,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'category_id' => $visitingCardsCategory->id ?? 1,
                'name' => 'Classic Business Cards',
                'description' => 'Classic design business cards with same day delivery in Mumbai & Hyderabad. New design options available with premium finishes.',
                'short_description' => 'Classic business cards with new design options.',
                'price' => 250.00,
                'sale_price' => 200.00,
                'stock_quantity' => 300,
                'featured_image' => 'products/visiting-cards/classic.jpg',
                'rating' => 4.6,
                'reviews_count' => 545,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'category_id' => $visitingCardsCategory->id ?? 1,
                'name' => 'Premium Business Cards',
                'description' => 'Premium quality business cards with luxury finishes. Matt lamination and spot UV options available.',
                'short_description' => 'Premium business cards with luxury finishes.',
                'price' => 350.00,
                'sale_price' => 299.00,
                'stock_quantity' => 200,
                'featured_image' => 'products/visiting-cards/premium.jpg',
                'rating' => 4.8,
                'reviews_count' => 324,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'category_id' => $visitingCardsCategory->id ?? 1,
                'name' => 'Digital Business Cards',
                'description' => 'Modern digital business cards with QR codes and NFC technology. Perfect for tech-savvy professionals.',
                'short_description' => 'Digital business cards with QR codes and NFC.',
                'price' => 150.00,
                'sale_price' => null,
                'stock_quantity' => 100,
                'featured_image' => 'products/visiting-cards/digital.jpg',
                'rating' => 4.5,
                'reviews_count' => 234,
                'is_featured' => false,
                'is_active' => true
            ],
            [
                'category_id' => $visitingCardsCategory->id ?? 1,
                'name' => 'Eco-Friendly Business Cards',
                'description' => 'Environmentally friendly business cards made from recycled materials. Available in various eco-friendly finishes.',
                'short_description' => 'Eco-friendly cards made from recycled materials.',
                'price' => 180.00,
                'sale_price' => null,
                'stock_quantity' => 150,
                'featured_image' => 'products/visiting-cards/eco-friendly.jpg',
                'rating' => 4.4,
                'reviews_count' => 167,
                'is_featured' => false,
                'is_active' => true
            ],

            // Stationery Products
            [
                'category_id' => $stationeryCategory->id ?? 2,
                'name' => 'Corporate Letterheads',
                'description' => 'Professional corporate letterheads with your company branding. Premium paper quality with various design options.',
                'short_description' => 'Professional corporate letterheads with premium quality.',
                'price' => 300.00,
                'sale_price' => null,
                'stock_quantity' => 200,
                'featured_image' => 'products/stationery/letterhead.jpg',
                'rating' => 4.7,
                'reviews_count' => 445,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'category_id' => $stationeryCategory->id ?? 2,
                'name' => 'Custom Notebooks',
                'description' => 'Personalized notebooks with custom covers and branding. Available in A4, A5, and pocket sizes.',
                'short_description' => 'Personalized notebooks with custom covers.',
                'price' => 250.00,
                'sale_price' => 220.00,
                'stock_quantity' => 180,
                'featured_image' => 'products/stationery/notebook.jpg',
                'rating' => 4.5,
                'reviews_count' => 298,
                'is_featured' => false,
                'is_active' => true
            ],

            // Winter Wear
            [
                'category_id' => $winterWearCategory->id ?? 11,
                'name' => 'Custom Hoodies',
                'description' => 'Premium quality custom hoodies with your design or logo. Available in various colors and sizes.',
                'short_description' => 'Premium custom hoodies with personalized designs.',
                'price' => 899.00,
                'sale_price' => 699.00,
                'stock_quantity' => 75,
                'featured_image' => 'products/winter-wear/hoodie.jpg',
                'rating' => 4.8,
                'reviews_count' => 523,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'category_id' => $winterWearCategory->id ?? 11,
                'name' => 'Custom Jackets',
                'description' => 'Stylish custom jackets perfect for corporate events and team building. Water-resistant and comfortable.',
                'short_description' => 'Stylish custom jackets for corporate events.',
                'price' => 1299.00,
                'sale_price' => 999.00,
                'stock_quantity' => 50,
                'featured_image' => 'products/winter-wear/jacket.jpg',
                'rating' => 4.9,
                'reviews_count' => 234,
                'is_featured' => true,
                'is_active' => true
            ],

            // Gifts & Mugs
            [
                'category_id' => $giftsCategory->id ?? 7,
                'name' => 'Photo Mugs',
                'description' => 'Personalized photo mugs with your favorite memories. High-quality printing that lasts.',
                'short_description' => 'Personalized photo mugs with lasting quality.',
                'price' => 199.00,
                'sale_price' => 149.00,
                'stock_quantity' => 300,
                'featured_image' => 'products/gifts/photo-mug.jpg',
                'rating' => 4.6,
                'reviews_count' => 672,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'category_id' => $giftsCategory->id ?? 7,
                'name' => 'Custom Photo Albums',
                'description' => 'Beautiful custom photo albums to preserve your precious memories. Various sizes and binding options available.',
                'short_description' => 'Custom photo albums for preserving memories.',
                'price' => 599.00,
                'sale_price' => null,
                'stock_quantity' => 120,
                'featured_image' => 'products/gifts/photo-album.jpg',
                'rating' => 4.8,
                'reviews_count' => 387,
                'is_featured' => false,
                'is_active' => true
            ]
        ];

        // Create products with proper slugs and SKUs
        foreach ($products as $index => $productData) {
            $slug = Str::slug($productData['name']);
            $sku = 'PRD-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            Product::create([
                'category_id' => $productData['category_id'],
                'name' => $productData['name'],
                'slug' => $slug,
                'description' => $productData['description'],
                'short_description' => $productData['short_description'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'sku' => $sku,
                'stock_quantity' => $productData['stock_quantity'],
                'weight' => rand(50, 500) / 100, // Random weight between 0.5-5kg
                'dimensions' => $this->getRandomDimensions(),
                'attributes' => $this->getRandomAttributes(),
                'featured_image' => $productData['featured_image'],
                'is_featured' => $productData['is_featured'],
                'is_active' => $productData['is_active'],
                'views' => rand(100, 1000),
                'rating' => $productData['rating'],
                'reviews_count' => $productData['reviews_count'],
            ]);
        }
    }

    private function getRandomDimensions()
    {
        $dimensions = [
            '9cm x 5cm',
            '10cm x 7cm',
            '8.5cm x 5.5cm',
            '21cm x 29.7cm', // A4
            '14.8cm x 21cm',  // A5
            '30cm x 20cm',
            '25cm x 15cm'
        ];
        
        return $dimensions[array_rand($dimensions)];
    }

    private function getRandomAttributes()
    {
        $attributes = [
            [
                'material' => 'Premium Paper',
                'finish' => 'Glossy',
                'thickness' => '350gsm'
            ],
            [
                'material' => 'Cotton Blend',
                'color' => 'Multiple',
                'sizes' => ['S', 'M', 'L', 'XL']
            ],
            [
                'material' => 'Ceramic',
                'capacity' => '320ml',
                'dishwasher_safe' => true
            ],
            [
                'material' => 'Recycled Paper',
                'binding' => 'Perfect Bound',
                'pages' => '100'
            ]
        ];

        return $attributes[array_rand($attributes)];
    }
}
