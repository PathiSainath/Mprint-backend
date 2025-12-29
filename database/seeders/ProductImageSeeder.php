<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductImage;

class ProductImageSeeder extends Seeder
{
    public function run()
    {
        // Get all products
        $products = Product::all();

        // Sample image URLs (you can replace these with actual images later)
        $sampleImages = [
            // Business Cards Images
            'visiting-cards' => [
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcSUygyApbmXBXk3A5PhVkmjEcNydo-aI14bY0MhLJwzDeetMLaMM3dAaYcL7GIdZLumjjtvmPHjRrBaRfT8oC3MCLv9CYpxGIqwIMmkKf8&usqp=CAc',
                'https://encrypted-tbn2.gstatic.com/shopping?q=tbn:ANd9GcROxZDhT3_fbk1BAvbF3cmnmtmlWq6mo-VhFzoC3bY8-53Wgh-N8m0FEeHYhwKvXghaPSnuMQvBduecxZqAhshYPsSIP8OqgYIaxIdeUp8i&usqp=CAc',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcTFvhKDIhzqhzrK1XyPG4Qk1OYKpMJTgUAK2w&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcR9vXW2vFdKwGz5C5lQh8hSkPXJKqEPRKjKeg&s'
            ],
            // Stationery Images  
            'stationery' => [
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQv8fHOsKpxOzhsXkLhMoVtqQDYoiLjzQYgNw&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQZ5YPJ8xT8U4QTQsH5RfXPJJH8fLs-mJJ7-g&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcSgLXvK5HGTzHuQyX5x2zGPY1YpMNpzwvR8_g&s'
            ],
            // Winter Wear Images
            'winter-wear' => [
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQtL8XU5qzh7YnXwPuH4cDGxUgHnBh7pY8VzQ&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcRrQWcUqTXHD5vPyXzMHnW3J9BFpZQ9YBZn2g&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQBGrHnKLs9Q3XQTKs1ZpXYfJ7tHHQRsKBKbg&s'
            ],
            // Gifts Images
            'gifts' => [
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcRZ7qGJU4vVwH3h8zN5mRKGXQJDYzx9vGpzNw&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQQtLr8x5zU4WvH8nD5lBKgXoJYyzp9bGpzRw&s',
                'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcRBBrHkKLs9Q1XQTKs1ZpMQgJ7tHNQRsKTKag&s'
            ]
        ];

        foreach ($products as $product) {
            // Determine product category type for image selection
            $categoryType = 'visiting-cards'; // Default
            
            if ($product->category) {
                $categorySlug = $product->category->slug;
                if (strpos($categorySlug, 'stationery') !== false) {
                    $categoryType = 'stationery';
                } elseif (strpos($categorySlug, 'winter-wear') !== false) {
                    $categoryType = 'winter-wear';
                } elseif (strpos($categorySlug, 'gifts') !== false || strpos($categorySlug, 'mugs') !== false) {
                    $categoryType = 'gifts';
                }
            }

            // Add 2-4 random gallery images for each product
            $imageCount = rand(2, 4);
            $availableImages = $sampleImages[$categoryType];
            $selectedImages = array_rand($availableImages, min($imageCount, count($availableImages)));
            
            if (!is_array($selectedImages)) {
                $selectedImages = [$selectedImages];
            }

            foreach ($selectedImages as $index => $imageIndex) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => 'products/' . $categoryType . '/gallery_' . ($index + 1) . '_' . $product->id . '.jpg',
                    'alt_text' => $product->name . ' - View ' . ($index + 1),
                    'sort_order' => $index,
                    'is_primary' => $index === 0 && !$product->featured_image // Set first as primary if no featured image
                ]);
            }
        }

        $this->command->info('✅ Product gallery images seeded successfully!');
    }
}
