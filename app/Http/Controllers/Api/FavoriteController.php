<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FavoriteController extends Controller
{
    /**
     * GET /api/favorites
     * Get all favorites for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $favorites = Favorite::where('user_id', $request->user()->id)
                ->with(['product.category', 'product.images'])
                ->orderBy('created_at', 'desc')
                ->get();

            $products = $favorites->map(function ($favorite) {
                $product = $favorite->product;

                if (!$product) {
                    return null;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'current_price' => $product->current_price,
                    'discount_percentage' => $product->discount_percentage,
                    'featured_image' => $product->featured_image,
                    'featured_image_url' => $product->featured_image_url,
                    'category' => $product->category,
                    'is_featured' => $product->is_featured,
                    'stock_quantity' => $product->stock_quantity,
                    'stock_status' => $product->stock_status,
                    'rating' => $product->rating,
                    'reviews_count' => $product->reviews_count,
                    'favorited_at' => $favorite->created_at,
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => $products->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Get favorites error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching favorites.'
            ], 500);
        }
    }

    /**
     * POST /api/favorites/add
     * Add a product to favorites
     */
    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        try {
            $userId = $request->user()->id;
            $productId = $request->product_id;

            // Check if already favorited
            $existing = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product already in favorites',
                    'is_favorited' => true
                ]);
            }

            // Add to favorites
            Favorite::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);

            // Get updated count
            $count = Favorite::where('user_id', $userId)->count();

            return response()->json([
                'success' => true,
                'message' => 'Product added to favorites',
                'is_favorited' => true,
                'count' => $count
            ], 201);
        } catch (\Exception $e) {
            Log::error('Add favorite error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error adding to favorites.'
            ], 500);
        }
    }

    /**
     * DELETE /api/favorites/remove/{productId}
     * Remove a product from favorites
     */
    public function remove(Request $request, $productId): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $favorite = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();

            if (!$favorite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not in favorites'
                ], 404);
            }

            $favorite->delete();

            // Get updated count
            $count = Favorite::where('user_id', $userId)->count();

            return response()->json([
                'success' => true,
                'message' => 'Product removed from favorites',
                'is_favorited' => false,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Remove favorite error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error removing from favorites.'
            ], 500);
        }
    }

    /**
     * POST /api/favorites/toggle
     * Toggle favorite status for a product
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        try {
            $userId = $request->user()->id;
            $productId = $request->product_id;

            $favorite = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();

            if ($favorite) {
                // Remove from favorites
                $favorite->delete();
                $isFavorited = false;
                $message = 'Product removed from favorites';
            } else {
                // Add to favorites
                Favorite::create([
                    'user_id' => $userId,
                    'product_id' => $productId
                ]);
                $isFavorited = true;
                $message = 'Product added to favorites';
            }

            // Get updated count
            $count = Favorite::where('user_id', $userId)->count();

            return response()->json([
                'success' => true,
                'message' => $message,
                'is_favorited' => $isFavorited,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Toggle favorite error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error toggling favorite.'
            ], 500);
        }
    }

    /**
     * GET /api/favorites/check/{productId}
     * Check if a product is favorited
     */
    public function check(Request $request, $productId): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $isFavorited = Favorite::where('user_id', $userId)
                ->where('product_id', $productId)
                ->exists();

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited
            ]);
        } catch (\Exception $e) {
            Log::error('Check favorite error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking favorite status.'
            ], 500);
        }
    }

    /**
     * GET /api/favorites/count
     * Get count of favorites for authenticated user
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $count = Favorite::where('user_id', $request->user()->id)->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Count favorites error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'count' => 0
            ], 500);
        }
    }

    /**
     * DELETE /api/favorites/clear
     * Clear all favorites for authenticated user
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            Favorite::where('user_id', $request->user()->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'All favorites cleared',
                'count' => 0
            ]);
        } catch (\Exception $e) {
            Log::error('Clear favorites error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error clearing favorites.'
            ], 500);
        }
    }
}
