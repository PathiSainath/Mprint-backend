<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

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

            // Prepare email data
            $emailData = [
                'user_name' => $request->user()->name,
                'user_email' => $request->user()->email,
                'order_number' => $order->order_number,
                'transaction_id' => $order->transaction_id,
                'invoice_id' => $order->invoice_id,
                'product_name' => $order->orderItems->first()->product_name ?? 'N/A',
                'issue_type' => $validated['issue_type'],
                'description' => $validated['description'],
                'image_paths' => $imagePaths,
                'submitted_at' => now()->format('Y-m-d H:i:s'),
            ];

            // TODO: Send email to admin using Resend service
            // For now, we'll just return success
            // \Mail::to(config('mail.admin_email'))->send(new ComplaintMail($emailData));

            return response()->json([
                'success' => true,
                'message' => 'Your complaint has been submitted successfully. Our team will review it shortly.',
                'data' => $emailData
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
