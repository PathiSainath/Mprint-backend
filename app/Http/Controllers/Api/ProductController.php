<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\ParameterBag;

class ProductController extends Controller
{
    private function imageValidationRules(): array
    {
        return [
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp,gif|max:20480', // 20MB
        ];
    }

    private function imageValidationMessages(): array
    {
        return [
            'images.max' => 'You can upload a maximum of 10 images.',
            'images.*.image' => 'Each file must be a valid image.',
            'images.*.mimes' => 'Images must be JPEG, JPG, PNG, WEBP, or GIF format.',
            'images.*.max' => 'Each image must not exceed 20MB.',
        ];
    }

    /**
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'images']);

        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('category_slug')) {
            $cat = Category::where('slug', $request->category_slug)->first();
            if ($cat) $query->where('category_id', $cat->id);
        }
        if ($request->filled('featured')) $query->featured();
        if ($request->filled('in_stock')) $query->inStock();

        // Price filtering
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float)$request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float)$request->max_price);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder)->active();

        $perPage = (int)$request->get('per_page', 12);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(fn($p) => $this->appendImageUrls($p));

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * GET /api/products/category/{slug}
     */
    public function byCategory(Request $request, $categorySlug): JsonResponse
    {
        try {
            $category = Category::where('slug', $categorySlug)->firstOrFail();

            $query = Product::where('category_id', $category->id)->active()->with('images');

            // Price filtering
            if ($request->filled('min_price')) {
                $query->where('price', '>=', (float)$request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', (float)$request->max_price);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = (int)$request->get('per_page', 12);
            $products = $query->paginate($perPage);

            $products->getCollection()->transform(fn($p) => $this->appendImageUrls($p));

            // Get price range for this category
            $priceRange = Product::where('category_id', $category->id)
                ->active()
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $products,
                'category' => $category,
                'price_range' => [
                    'min' => (float)($priceRange->min_price ?? 0),
                    'max' => (float)($priceRange->max_price ?? 10000)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('byCategory error: ' . $e->getMessage(), ['slug' => $categorySlug]);
            return response()->json(['success' => false, 'message' => 'Category not found or error fetching products.'], 404);
        }
    }

    /**
     * GET /api/products/featured
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $perPage = (int)$request->get('per_page', 12);

            $products = Product::with(['category', 'images'])
                ->active()
                ->where('is_featured', true)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $products->getCollection()->transform(fn($p) => $this->appendImageUrls($p));

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Featured products fetched successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('featured error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching featured products.'
            ], 500);
        }
    }

    /**
     * GET /api/products/new-arrivals
     */
    public function newArrivals(Request $request): JsonResponse
    {
        try {
            $perPage = (int)$request->get('per_page', 24);

            $products = Product::with(['category', 'images'])
                ->active()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $products->getCollection()->transform(fn($p) => $this->appendImageUrls($p));

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'New arrivals fetched successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('newArrivals error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching new arrivals.'
            ], 500);
        }
    }

    /**
     * GET /api/products/{slug}
     */
    public function show($slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->active()->with(['category', 'images'])->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $product->increment('views');

        return response()->json(['success' => true, 'data' => $this->appendImageUrls($product)]);
    }

    /**
     * GET /api/products/{slug}/related
     * Returns products from the same category, excluding the current product
     */
    public function relatedProducts(Request $request, $slug): JsonResponse
    {
        try {
            $product = Product::where('slug', $slug)->with('category')->first();

            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $limit = (int)$request->get('limit', 8);

            $relatedProducts = Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->active()
                ->with(['category', 'images'])
                ->orderByRaw('RAND()')
                ->limit($limit)
                ->get();

            $relatedProducts->transform(fn($p) => $this->appendImageUrls($p));

            return response()->json([
                'success' => true,
                'data' => $relatedProducts,
                'category' => $product->category,
                'message' => 'Related products fetched successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('relatedProducts error: ' . $e->getMessage(), ['slug' => $slug, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching related products.'
            ], 500);
        }
    }

    /**
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            array_merge([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'short_description' => 'nullable|string|max:1000',
                'price' => 'required|numeric|min:0.01',
                'sale_price' => 'nullable|numeric|min:0',
                'sku' => 'nullable|string|unique:products,sku',
                'stock_quantity' => 'required|integer|min:0',
                'weight' => 'nullable|numeric|min:0',
                'dimensions' => 'nullable|string',
                'attributes' => 'nullable',
                'is_featured' => 'nullable|in:0,1,true,false',
                'is_active' => 'nullable|in:0,1,true,false',
            ], $this->imageValidationRules()),
            array_merge([
                'category_id.required' => 'Please select a category.',
                'name.required' => 'Product name is required.',
                'description.required' => 'Product description is required.',
                'price.required' => 'Product price is required.',
            ], $this->imageValidationMessages())
        );

        $validator->after(function ($v) use ($request) {
            if ($request->filled('sale_price') && $request->filled('price') && (float)$request->sale_price >= (float)$request->price) {
                $v->errors()->add('sale_price', 'Sale price must be less than regular price.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $attributes = $this->parseAttributes($request->input('attributes'));

            // slug uniqueness
            $slug = $request->slug ?: Str::slug($request->name);
            $slug = $this->ensureUniqueSlug($slug);

            $product = Product::create([
                'category_id' => (int)$request->category_id,
                'name' => trim($request->name),
                'slug' => $slug,
                'description' => trim($request->description),
                'short_description' => $request->short_description ? trim($request->short_description) : null,
                'price' => (float)$request->price,
                'sale_price' => $request->filled('sale_price') ? (float)$request->sale_price : null,
                'sku' => $request->sku ?: 'PRD-' . time() . '-' . rand(1000, 9999),
                'stock_quantity' => (int)$request->stock_quantity,
                'weight' => $request->filled('weight') ? (float)$request->weight : null,
                'dimensions' => $request->filled('dimensions') ? trim($request->dimensions) : null,
                'attributes' => $attributes,
                'is_featured' => $this->toBool($request->is_featured),
                'is_active' => $this->toBool($request->is_active, true),
                'stock_status' => ((int)$request->stock_quantity > 0) ? 'in_stock' : 'out_of_stock',
            ]);

            if ($request->hasFile('images')) {
                $this->handleImageUploads($request, $product);
            }

            return response()->json(['success' => true, 'message' => 'Product created successfully', 'data' => $this->appendImageUrls($product->fresh(['images', 'category']))], 201);
        } catch (\Exception $e) {
            Log::error('store product error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'request' => $request->all()]);
            return response()->json(['success' => false, 'message' => 'Error creating product.'], 500);
        }
    }

    /**
     * PUT/PATCH /api/products/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make(
            $request->all(),
            array_merge([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'short_description' => 'nullable|string|max:1000',
                'price' => 'required|numeric|min:0.01',
                'sale_price' => 'nullable|numeric|min:0',
                'sku' => 'nullable|string|unique:products,sku,' . $product->id,
                'stock_quantity' => 'required|integer|min:0',
                'weight' => 'nullable|numeric|min:0',
                'dimensions' => 'nullable|string',
                'attributes' => 'nullable',
                'is_featured' => 'nullable|in:0,1,true,false',
                'is_active' => 'nullable|in:0,1,true,false',
            ], $this->imageValidationRules()),
            $this->imageValidationMessages()
        );

        $validator->after(function ($v) use ($request) {
            if ($request->filled('sale_price') && $request->filled('price') && (float)$request->sale_price >= (float)$request->price) {
                $v->errors()->add('sale_price', 'Sale price must be less than regular price.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $attributes = $this->parseAttributes($request->input('attributes'));

            $slug = $request->slug ?: Str::slug($request->name);
            $slug = $this->ensureUniqueSlug($slug, $product->id);

            $product->update([
                'category_id' => (int)$request->category_id,
                'name' => trim($request->name),
                'slug' => $slug,
                'description' => trim($request->description),
                'short_description' => $request->short_description ? trim($request->short_description) : null,
                'price' => (float)$request->price,
                'sale_price' => $request->filled('sale_price') ? (float)$request->sale_price : null,
                'sku' => $request->sku ?: $product->sku,
                'stock_quantity' => (int)$request->stock_quantity,
                'weight' => $request->filled('weight') ? (float)$request->weight : null,
                'dimensions' => $request->filled('dimensions') ? trim($request->dimensions) : null,
                'attributes' => $attributes,
                'is_featured' => $this->toBool($request->is_featured),
                'is_active' => $this->toBool($request->is_active, true),
                'stock_status' => ((int)$request->stock_quantity > 0) ? 'in_stock' : 'out_of_stock',
            ]);

            if ($request->hasFile('images')) {
                $this->deleteProductImages($product);
                $this->handleImageUploads($request, $product);
            }

            return response()->json(['success' => true, 'message' => 'Product updated successfully', 'data' => $this->appendImageUrls($product->fresh(['images', 'category']))]);
        } catch (\Exception $e) {
            Log::error('update product error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'request' => $request->all()]);
            return response()->json(['success' => false, 'message' => 'Error updating product.'], 500);
        }
    }

    /**
     * DELETE /api/products/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $product = Product::with('images')->findOrFail($id);

            $this->deleteProductImages($product);

            $product->delete();

            return response()->json(['success' => true, 'message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            Log::error('destroy product error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error deleting product.'], 500);
        }
    }

    /**
     * DELETE /api/products/images/{imageId}
     */
    public function deleteImage(Request $request, $imageId): JsonResponse
    {
        try {
            $image = ProductImage::findOrFail($imageId);
            $product = $image->product;

            Storage::disk('public')->delete($image->image_path);

            if ($image->is_primary) {
                $next = $product->images()->where('id', '!=', $image->id)->orderBy('sort_order')->first();
                if ($next) {
                    $next->is_primary = true;
                    $next->save();
                    $product->featured_image = $next->image_path;
                } else {
                    $product->featured_image = null;
                }
                $product->save();
            }

            $image->delete();

            return response()->json(['success' => true, 'message' => 'Image deleted successfully']);
        } catch (\Exception $e) {
            Log::error('deleteImage error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error deleting image.'], 500);
        }
    }

    /**
     * POST /api/products/{productId}/reorder-images
     * payload: images => [id1, id2, ...] or [{id: id1}, ...]
     */
    public function reorderImages(Request $request, $productId): JsonResponse
    {
        $request->validate(['images' => 'required|array']);

        try {
            $product = Product::with('images')->findOrFail($productId);
            $images = $request->input('images');

            $orderedIds = array_map(function ($item) {
                if (is_array($item) && isset($item['id'])) return (int)$item['id'];
                if (is_object($item) && isset($item->id)) return (int)$item->id;
                return (int)$item;
            }, $images);

            foreach ($orderedIds as $index => $id) {
                $img = $product->images()->where('id', $id)->first();
                if ($img) {
                    $img->sort_order = $index;
                    $img->save();
                }
            }

            return response()->json(['success' => true, 'message' => 'Images reordered']);
        } catch (\Exception $e) {
            Log::error('reorderImages error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error reordering images.'], 500);
        }
    }

    /**
     * PUT /api/products/{productId}/primary/{imageId}
     */
    public function setPrimaryImage(Request $request, $productId, $imageId): JsonResponse
    {
        try {
            $product = Product::with('images')->findOrFail($productId);
            $image = $product->images()->where('id', $imageId)->firstOrFail();

            $product->images()->update(['is_primary' => false]);

            $image->is_primary = true;
            $image->save();

            $product->featured_image = $image->image_path;
            $product->save();

            return response()->json(['success' => true, 'message' => 'Primary image set']);
        } catch (\Exception $e) {
            Log::error('setPrimaryImage error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error setting primary image.'], 500);
        }
    }

    /**
     * Append full URLs for featured and gallery images
     */
    private function appendImageUrls(Product $product): array
    {
        $data = $product->toArray();

        $data['featured_image_url'] = $product->featured_image ? asset('storage/' . $product->featured_image) : null;

        $data['images'] = $product->images->map(function ($img) {
            return [
                'id' => $img->id,
                'image_path' => $img->image_path,
                'image_url' => asset('storage/' . $img->image_path),
                'alt_text' => $img->alt_text,
                'sort_order' => $img->sort_order,
                'is_primary' => (bool)$img->is_primary,
            ];
        })->toArray();

        return $data;
    }

    /**
     * Convert various truthy values to boolean
     */
    private function toBool($value, $default = false): bool
    {
        if ($value === null || $value === '') return $default;
        if (is_string($value)) return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        return (bool)$value;
    }

    /**
     * Parse attributes input (string JSON, array, ParameterBag)
     */
    private function parseAttributes($attributes): array
    {
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            return is_array($decoded) ? $decoded : [];
        }

        if ($attributes instanceof ParameterBag) {
            return $attributes->all();
        }

        if (is_array($attributes)) {
            return $attributes;
        }

        return [];
    }

    /**
     * Ensure slug uniqueness. If $ignoreId provided, ignore that product id
     */
    private function ensureUniqueSlug(string $slug, $ignoreId = null): string
    {
        $base = $slug;
        $i = 1;
        while (Product::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Handle image uploads using Intervention Image v3
     */
    private function handleImageUploads(Request $request, Product $product): void
    {
        $featured = null;
        $files = $request->file('images') ?: [];

        foreach ($files as $index => $file) {
            try {
                // read image (v3)
                $img = Image::read($file);

                // scaleDown: keep aspect ratio, don't upscale, largest side => 2000
                $img = $img->scaleDown(2000);

                $filename = time() . '_' . $index . '_' . Str::random(8) . '.jpg';
                $relative = 'products/' . $filename;
                $absolute = storage_path('app/public/' . $relative);

                // ensure directory exists
                if (!is_dir(dirname($absolute))) {
                    mkdir(dirname($absolute), 0755, true);
                }

                // save as jpeg quality 75
                $img->toJpeg(75)->save($absolute);

                if ($index === 0) $featured = $relative;

                $maxOrder = $product->images()->max('sort_order');
                $nextOrder = is_null($maxOrder) ? 0 : ($maxOrder + 1);

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $relative,
                    'alt_text' => $product->name,
                    'sort_order' => $nextOrder,
                    'is_primary' => $nextOrder === 0,
                ]);
            } catch (\Exception $e) {
                Log::error('handleImageUploads error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                // continue to next image rather than failing whole request
                continue;
            }
        }

        if ($featured) {
            $product->update(['featured_image' => $featured]);
        }
    }

    /**
     * Delete all product images (files + records)
     */
    private function deleteProductImages(Product $product): void
    {
        if ($product->featured_image) {
            Storage::disk('public')->delete($product->featured_image);
        }

        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->image_path);
            $img->delete();
        }
    }
}
