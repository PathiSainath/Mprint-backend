<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OfferBar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class OfferBarController extends Controller
{
    /**
     * GET /api/offer-bars
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = OfferBar::query();

            // Filter by active status
            if ($request->filled('active_only') && $request->active_only) {
                $query->active()->current();
            }

            $offerBars = $query->orderBy('sort_order')->orderBy('id', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $offerBars
            ]);
        } catch (\Exception $e) {
            Log::error('OfferBar index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching offer bars'
            ], 500);
        }
    }

    /**
     * POST /api/offer-bars
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'background_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $offerBar = OfferBar::create([
                'message' => $request->message,
                'background_color' => $request->background_color ?? '#000000',
                'text_color' => $request->text_color ?? '#ffffff',
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->is_active ?? true,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offer bar created successfully',
                'data' => $offerBar
            ], 201);
        } catch (\Exception $e) {
            Log::error('OfferBar store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating offer bar'
            ], 500);
        }
    }

    /**
     * PUT /api/offer-bars/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $offerBar = OfferBar::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'background_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $offerBar->update([
                'message' => $request->message,
                'background_color' => $request->background_color ?? $offerBar->background_color,
                'text_color' => $request->text_color ?? $offerBar->text_color,
                'sort_order' => $request->sort_order ?? $offerBar->sort_order,
                'is_active' => $request->is_active ?? $offerBar->is_active,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offer bar updated successfully',
                'data' => $offerBar
            ]);
        } catch (\Exception $e) {
            Log::error('OfferBar update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating offer bar'
            ], 500);
        }
    }

    /**
     * DELETE /api/offer-bars/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $offerBar = OfferBar::findOrFail($id);
            $offerBar->delete();

            return response()->json([
                'success' => true,
                'message' => 'Offer bar deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('OfferBar delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting offer bar'
            ], 500);
        }
    }
}
