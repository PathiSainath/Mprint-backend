<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'sale_price',
        'sku',
        'stock_quantity',
        'stock_status',
        'weight',
        'dimensions',
        'attributes', // ✅ JSON column for category-specific attributes
        'featured_image',
        'is_featured',
        'is_active',
        'views',
        'rating',
        'reviews_count'
    ];

protected $casts = [
    'price' => 'decimal:2',
    'sale_price' => 'decimal:2',
    'weight' => 'decimal:2',
    'rating' => 'decimal:1',
    'attributes' => 'array',
    'is_featured' => 'boolean',
    'is_active' => 'boolean'
];


    protected $appends = [
        'featured_image_url',
        'discount_percentage',
        'current_price'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the category that owns the product
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all images for the product
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to get products in stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock')
                     ->where('stock_quantity', '>', 0);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors (Computed Attributes)
    |--------------------------------------------------------------------------
    */

    /**
     * Get the featured image URL
     */
    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }
        return null;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentageAttribute()
    {
        if ($this->sale_price && $this->price && $this->sale_price < $this->price) {
            return round((($this->price - $this->sale_price) / $this->price) * 100);
        }
        return 0;
    }

    /**
     * Get current effective price (sale price or regular price)
     */
    public function getCurrentPriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Get all images with proper URLs
     */
    public function getImagesWithUrlsAttribute()
    {
        $images = [];
        
        // Add featured image first if exists
        if ($this->featured_image) {
            $images[] = [
                'id' => 'featured',
                'image_path' => $this->featured_image,
                'image_url' => asset('storage/' . $this->featured_image),
                'alt_text' => $this->name . ' - Featured Image',
                'sort_order' => -1,
                'is_primary' => true
            ];
        }
        
        // Add gallery images
        if ($this->relationLoaded('images') && $this->images->count() > 0) {
            foreach ($this->images as $image) {
                $images[] = [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => asset('storage/' . $image->image_path),
                    'alt_text' => $image->alt_text ?? $this->name,
                    'sort_order' => $image->sort_order ?? 0,
                    'is_primary' => $image->is_primary ?? false
                ];
            }
        }
        
        return collect($images)->sortBy('sort_order')->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ Category-Specific Attribute Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get category-specific attribute configuration
     * This defines what attributes each category should have
     */
    public function getCategoryAttributeTemplateAttribute()
    {
        $categorySlug = strtolower($this->category->slug ?? 'default');
        
        $templates = [
            // ✅ Visiting Cards
            'visiting-cards' => [
                'delivery_speed' => ['type' => 'radio', 'label' => 'Delivery Speed', 'required' => true],
                'shape' => ['type' => 'select', 'label' => 'Shape', 'required' => true],
                'size' => ['type' => 'select', 'label' => 'Size', 'required' => true],
                'material' => ['type' => 'select', 'label' => 'Material', 'required' => true],
                'quantity' => ['type' => 'quantity', 'label' => 'Quantity', 'required' => true]
            ],
            
            // ✅ T-Shirts
            't-shirts' => [
                'delivery_speed' => ['type' => 'radio', 'label' => 'Delivery Speed', 'required' => true],
                'size' => ['type' => 'select', 'label' => 'Size', 'required' => true],
                'color' => ['type' => 'color', 'label' => 'Color', 'required' => true],
                'fabric' => ['type' => 'select', 'label' => 'Fabric', 'required' => false],
                'quantity' => ['type' => 'quantity', 'label' => 'Quantity', 'required' => true]
            ],
            
            // ✅ Banners
            'banners' => [
                'delivery_speed' => ['type' => 'radio', 'label' => 'Delivery Speed', 'required' => true],
                'size' => ['type' => 'select', 'label' => 'Size', 'required' => true],
                'material' => ['type' => 'select', 'label' => 'Material', 'required' => true],
                'finish' => ['type' => 'select', 'label' => 'Finish', 'required' => false],
                'quantity' => ['type' => 'quantity', 'label' => 'Quantity', 'required' => true]
            ],
            
            // ✅ Mugs
            'mugs' => [
                'delivery_speed' => ['type' => 'radio', 'label' => 'Delivery Speed', 'required' => true],
                'type' => ['type' => 'select', 'label' => 'Type', 'required' => true],
                'color' => ['type' => 'color', 'label' => 'Color', 'required' => true],
                'quantity' => ['type' => 'quantity', 'label' => 'Quantity', 'required' => true]
            ],
            
            // ✅ Add more categories as needed...
        ];
        
        return $templates[$categorySlug] ?? [];
    }

    /**
     * Check if product has category-specific attributes
     */
    public function hasAttributes()
    {
        return !empty($this->attributes) && is_array($this->attributes);
    }

    /**
     * Get specific attribute options
     */
    public function getAttributeOptions($attributeName)
    {
        if ($this->hasAttributes() && isset($this->attributes[$attributeName])) {
            return $this->attributes[$attributeName];
        }
        return [];
    }

    /**
     * Validate if all required attributes are set
     */
    public function hasRequiredAttributes()
    {
        $template = $this->category_attribute_template;
        
        foreach ($template as $attrName => $config) {
            if ($config['required'] && !$this->getAttributeOptions($attrName)) {
                return false;
            }
        }
        
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if product is on sale
     */
    public function isOnSale()
    {
        return $this->sale_price && $this->sale_price < $this->price;
    }

    /**
     * Check if product is in stock
     */
    public function isInStock()
    {
        return $this->stock_status === 'in_stock' && $this->stock_quantity > 0;
    }

    /**
     * Decrement stock quantity
     */
    public function decrementStock($quantity = 1)
    {
        if ($this->stock_quantity >= $quantity) {
            $this->decrement('stock_quantity', $quantity);
            
            if ($this->stock_quantity <= 0) {
                $this->update(['stock_status' => 'out_of_stock']);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Increment stock quantity
     */
    public function incrementStock($quantity = 1)
    {
        $this->increment('stock_quantity', $quantity);
        
        if ($this->stock_quantity > 0 && $this->stock_status === 'out_of_stock') {
            $this->update(['stock_status' => 'in_stock']);
        }
        
        return true;
    }

    /**
     * Increment views count
     */
    public function incrementViews()
    {
        $this->increment('views');
    }

    /**
     * Update product rating
     */
    public function updateRating($newRating, $reviewsCount = null)
    {
        if ($reviewsCount !== null) {
            $this->update([
                'rating' => $newRating,
                'reviews_count' => $reviewsCount
            ]);
        } else {
            $this->update(['rating' => $newRating]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Method
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = \Illuminate\Support\Str::slug($product->name);
            }
        });

        // Update slug if name changes
        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = \Illuminate\Support\Str::slug($product->name);
            }
        });
    }
}
