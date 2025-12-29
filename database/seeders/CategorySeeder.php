<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Visiting Cards',
                'path' => '/visiting-cards',
                'description' => 'Professional business cards for every need',
                'sort_order' => 1,
                'is_featured' => true
            ],
            [
                'name' => 'Stationery, Letterheads & Notebooks',
                'path' => '/stationery',
                'description' => 'Custom stationery and office supplies',
                'sort_order' => 2,
                'is_featured' => true
            ],
            [
                'name' => 'Stamps and Ink',
                'path' => '/stamps',
                'description' => 'Custom stamps and ink solutions',
                'sort_order' => 3
            ],
            [
                'name' => 'Signs, Posters & Marketing Materials',
                'path' => '/signs',
                'description' => 'Marketing and promotional materials',
                'sort_order' => 4,
                'is_featured' => true
            ],
            [
                'name' => 'Labels, Stickers & Packaging',
                'path' => '/labels',
                'description' => 'Custom labels and packaging solutions',
                'sort_order' => 5
            ],
            [
                'name' => 'Clothing, Caps & Bags',
                'path' => '/clothing',
                'description' => 'Custom apparel and accessories',
                'sort_order' => 6,
                'is_featured' => true
            ],
            [
                'name' => 'Mugs, Albums & Gifts',
                'path' => '/gifts',
                'description' => 'Personalized gifts and photo products',
                'sort_order' => 7
            ],
            [
                'name' => 'Bulk Orders',
                'path' => '/bulk-orders',
                'description' => 'Bulk printing solutions for businesses',
                'sort_order' => 8
            ],
            [
                'name' => 'Custom Drinkware',
                'path' => '/drinkware',
                'description' => 'Custom mugs, bottles, and drinkware',
                'sort_order' => 9
            ],
            [
                'name' => 'Custom Polo T-shirts',
                'path' => '/polo-tshirts',
                'description' => 'Premium custom polo t-shirts',
                'sort_order' => 10,
                'is_featured' => true
            ],
            [
                'name' => 'Custom Winter Wear',
                'path' => '/winter-wear',
                'description' => 'Custom hoodies, jackets, and winter clothing',
                'sort_order' => 11,
                'is_featured' => true
            ]
        ];

        foreach ($categories as $categoryData) {
            $categoryData['slug'] = Str::slug($categoryData['name']);
            Category::create($categoryData);
        }
    }
}
