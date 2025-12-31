<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class CartController extends Controller
{
    private function normalizeAttributes(array $attrs): array
    {
        ksort($attrs);
        foreach ($attrs as $k => $v) {
            if (is_array($v)) $attrs[$k] = $this->normalizeAttributes($v);
        }
        return $attrs;
    }

    // GET /api/cart (auth required)
    public function index(Request $request)
    {
        try {
            $userId = auth()->id();

            $items = Cart::with(['product.category','product.images'])
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->get()
                ->filter(fn($i) => $i->product !== null);

            $formatted = $items->map(fn($item) => [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'price' => $item->product->price,
                    'featured_image_url' => $item->product->featured_image_url ?? '/placeholder.png',
                    'category' => optional($item->product->category)->name ?? 'No Category',
                ],
                'quantity' => $item->quantity,
                'selected_attributes' => $item->selected_attributes ?? [],
                'front_design_url' => $item->front_design_url,
                'back_design_url' => $item->back_design_url,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]);

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'count' => $items->count(),
                'total' => $items->sum('total_price'),
            ]);
        } catch (\Exception $e) {
            Log::error('Cart#index failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch cart'], 500);
        }
    }

    // POST /api/cart/add (auth required)
    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'selected_attributes' => 'nullable|array'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $userId = auth()->id();
            $product = Product::findOrFail($request->product_id);

            $unitPrice = $product->price;
            $qty = (int) $request->quantity;
            $attrs = $this->normalizeAttributes($request->input('selected_attributes', []));
            $attrsJson = json_encode($attrs);

            $existing = Cart::where('user_id', $userId)
                ->where('product_id', $request->product_id)
                ->where('selected_attributes', $attrsJson)
                ->first();

            if ($existing) {
                DB::transaction(function () use ($existing, $qty, $unitPrice) {
                    $existing->quantity += $qty;
                    $existing->unit_price = $unitPrice;
                    $existing->total_price = $existing->unit_price * $existing->quantity;
                    $existing->save();
                });
                return response()->json(['success' => true, 'message' => 'Cart updated', 'data' => $existing]);
            }

            $cartItem = Cart::create([
                'user_id' => $userId,
                'session_id' => null,
                'product_id' => $request->product_id,
                'quantity' => $qty,
                'selected_attributes' => $attrs,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $qty,
            ]);

            return response()->json(['success' => true, 'message' => 'Added to cart', 'data' => $cartItem], 201);
        } catch (\Exception $e) {
            Log::error('Cart#add failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to add to cart'], 500);
        }
    }

    // PUT /api/cart/update/{id} (auth required)
    public function updateQuantity(Request $request, $cartId)
    {
        $validator = Validator::make(['quantity' => $request->quantity], [
            'quantity' => 'required|integer|min:1'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $userId = auth()->id();
        $cart = Cart::where('user_id', $userId)->findOrFail($cartId);
        $cart->quantity = (int) $request->quantity;
        $cart->total_price = $cart->unit_price * $cart->quantity;
        $cart->save();

        return response()->json(['success' => true, 'data' => $cart]);
    }

    // DELETE /api/cart/remove/{id} (auth required)
    public function removeFromCart(Request $request, $cartId)
    {
        $userId = auth()->id();
        Cart::where('user_id', $userId)->findOrFail($cartId)->delete();
        return response()->json(['success' => true, 'message' => 'Item removed']);
    }

    // DELETE /api/cart/clear (auth required)
    public function clearCart(Request $request)
    {
        $userId = auth()->id();
        Cart::where('user_id', $userId)->delete();
        return response()->json(['success' => true, 'message' => 'Cart cleared']);
    }

    // GET /api/cart/count (auth required)
    public function getCartCount(Request $request)
    {
        $userId = auth()->id();
        $count = Cart::where('user_id', $userId)->sum('quantity');
        return response()->json(['success' => true, 'count' => $count]);
    }

    // GET /api/cart/total (auth required)
    public function getCartTotal(Request $request)
    {
        $userId = auth()->id();
        $total = Cart::where('user_id', $userId)->sum('total_price');
        return response()->json(['success' => true, 'total' => $total]);
    }

    // POST /api/cart/{cartId}/upload-designs (auth required)
    public function uploadDesigns(Request $request, $cartId)
    {
        $validator = Validator::make($request->all(), [
            'front_design' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB
            'back_design' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ], [
            'front_design.image' => 'Front design must be a valid image.',
            'front_design.mimes' => 'Front design must be JPG, PNG, or WebP.',
            'front_design.max' => 'Front design must not exceed 10MB.',
            'back_design.image' => 'Back design must be a valid image.',
            'back_design.mimes' => 'Back design must be JPG, PNG, or WebP.',
            'back_design.max' => 'Back design must not exceed 10MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $userId = auth()->id();
            $cart = Cart::where('user_id', $userId)->findOrFail($cartId);

            $frontPath = $cart->front_design_path;
            $backPath = $cart->back_design_path;

            // Handle front design upload
            if ($request->hasFile('front_design')) {
                // Delete old front design if exists
                if ($frontPath) {
                    Storage::disk('public')->delete($frontPath);
                }

                $frontPath = $this->processDesignUpload($request->file('front_design'), 'front');
            }

            // Handle back design upload
            if ($request->hasFile('back_design')) {
                // Delete old back design if exists
                if ($backPath) {
                    Storage::disk('public')->delete($backPath);
                }

                $backPath = $this->processDesignUpload($request->file('back_design'), 'back');
            }

            $cart->update([
                'front_design_path' => $frontPath,
                'back_design_path' => $backPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Designs uploaded successfully',
                'data' => [
                    'front_design_url' => $cart->fresh()->front_design_url,
                    'back_design_url' => $cart->fresh()->back_design_url,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Cart#uploadDesigns failed', ['e' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to upload designs'], 500);
        }
    }

    // DELETE /api/cart/{cartId}/designs/{side} (auth required)
    public function deleteDesign(Request $request, $cartId, $side)
    {
        if (!in_array($side, ['front', 'back'])) {
            return response()->json(['success' => false, 'message' => 'Invalid design side'], 400);
        }

        try {
            $userId = auth()->id();
            $cart = Cart::where('user_id', $userId)->findOrFail($cartId);

            $pathField = $side . '_design_path';
            $currentPath = $cart->{$pathField};

            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
                $cart->update([$pathField => null]);
            }

            return response()->json(['success' => true, 'message' => ucfirst($side) . ' design deleted']);
        } catch (\Exception $e) {
            Log::error('Cart#deleteDesign failed', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete design'], 500);
        }
    }

    // Helper method to process and save design uploads
    private function processDesignUpload($file, $side)
    {
        try {
            $img = Image::read($file);

            // Scale down if too large, maintaining aspect ratio
            $img = $img->scaleDown(2000);

            $filename = $side . '_' . time() . '_' . Str::random(8) . '.jpg';
            $relative = 'cart-designs/' . $filename;
            $absolute = storage_path('app/public/' . $relative);

            // Ensure directory exists
            if (!is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0755, true);
            }

            // Save as JPEG quality 85
            $img->toJpeg(85)->save($absolute);

            return $relative;
        } catch (\Exception $e) {
            Log::error('processDesignUpload failed', ['e' => $e->getMessage()]);
            throw $e;
        }
    }
}
