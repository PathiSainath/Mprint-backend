<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
}
