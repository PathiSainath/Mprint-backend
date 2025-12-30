<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Get all orders for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $orders = $request->user()->orders()
                ->with(['orderItems.product'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new order from cart or direct purchase
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|string',
            'shipping_city' => 'required|string',
            'shipping_state' => 'required|string',
            'shipping_zip' => 'required|string',
            'shipping_country' => 'nullable|string',
            'phone' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $orderItems = [];

            // Validate stock and calculate subtotal
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Check stock availability
                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}. Available: {$product->stock_quantity}"
                    ], 400);
                }

                $price = $product->sale_price ?? $product->price;
                $itemSubtotal = $price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'subtotal' => $itemSubtotal
                ];
            }

            // Tax and shipping are 0 as per requirement
            $tax = 0;
            $shipping = 0;
            $total = $subtotal + $tax + $shipping;

            // Generate unique IDs
            $orderNumber = Order::generateOrderNumber();
            $invoiceId = 'INV-' . strtoupper(Str::random(10));
            $transactionId = 'TXN-' . strtoupper(Str::random(10));

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'transaction_id' => $transactionId,
                'invoice_id' => $invoiceId,
                'payment_status' => 'pending', // COD
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_state' => $validated['shipping_state'],
                'shipping_zip' => $validated['shipping_zip'],
                'shipping_country' => $validated['shipping_country'] ?? 'India',
            ]);

            // Create order items and reduce stock
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                    'product_attributes' => null, // Can add customization later
                ]);

                // Reduce stock
                $item['product']->decrement('stock_quantity', $item['quantity']);
            }

            // Clear user's cart
            Cart::where('user_id', $request->user()->id)->delete();

            DB::commit();

            // Load relationships for response
            $order->load(['orderItems.product.images']);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific order by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $order = $request->user()->orders()
                ->with(['orderItems.product.images'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Send complaint/ticket email to admin
     */
    public function raiseTicket(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'required|exists:products,id',
            'issue_type' => 'required|string',
            'description' => 'required|string|min:10',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB max per image
        ]);

        try {
            // Verify the order belongs to the authenticated user
            $order = $request->user()->orders()
                ->with(['orderItems' => function ($query) use ($validated) {
                    $query->where('product_id', $validated['product_id']);
                }])
                ->findOrFail($validated['order_id']);

            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('complaint_images', 'public');
                    $imagePaths[] = $path;
                }
            }

            // Get product name from order items
            $productName = $order->orderItems->first()->product_name ?? 'N/A';

            // Save complaint to database
            $complaint = Complaint::create([
                'user_id' => $request->user()->id,
                'order_id' => $validated['order_id'],
                'product_id' => $validated['product_id'],
                'product_name' => $productName,
                'issue_type' => $validated['issue_type'],
                'description' => $validated['description'],
                'images' => $imagePaths,
                'status' => 'pending',
            ]);

            // TODO: Send email to admin using Resend service
            // \Mail::to(config('mail.admin_email'))->send(new ComplaintMail($complaint));

            return response()->json([
                'success' => true,
                'message' => 'Your complaint has been submitted successfully. Our team will review it shortly.',
                'data' => $complaint->load(['user', 'order', 'product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
