<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Complaint;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get all orders for admin panel
     */
    public function getOrders(Request $request)
    {
        try {
            $query = Order::with(['user', 'orderItems.product']);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $orders = $query->paginate($perPage);

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
     * Get all complaints/tickets for admin panel
     */
    public function getComplaints(Request $request)
    {
        try {
            $query = Complaint::with(['user', 'order', 'product']);

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('issue_type')) {
                $query->where('issue_type', $request->issue_type);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $complaints = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $complaints
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch complaints',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update complaint status and add admin response
     */
    public function updateComplaintStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_review,resolved,rejected',
            'admin_response' => 'nullable|string',
        ]);

        try {
            $complaint = Complaint::findOrFail($id);

            $complaint->update([
                'status' => $validated['status'],
                'admin_response' => $validated['admin_response'] ?? $complaint->admin_response,
                'resolved_at' => in_array($validated['status'], ['resolved', 'rejected'])
                    ? now()
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Complaint status updated successfully',
                'data' => $complaint->load(['user', 'order', 'product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,failed,refunded',
        ]);

        try {
            $order = Order::findOrFail($id);

            $order->update([
                'status' => $validated['status'],
                'payment_status' => $validated['payment_status'] ?? $order->payment_status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order->load(['user', 'orderItems.product'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $stats = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'delivered_orders' => Order::where('status', 'delivered')->count(),
                'total_complaints' => Complaint::count(),
                'pending_complaints' => Complaint::where('status', 'pending')->count(),
                'resolved_complaints' => Complaint::where('status', 'resolved')->count(),
                'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
                'pending_revenue' => Order::where('payment_status', 'pending')->sum('total'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
