<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    /**
     * GET /api/banners
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Banner::query();

            // Filter by type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Filter by active status
            if ($request->filled('active_only') && $request->active_only) {
                $query->active();
            }

            $banners = $query->orderBy('sort_order')->orderBy('id', 'desc')->get();

            // Append full image URLs
            $banners->transform(function ($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'description' => $banner->description,
                    'price_text' => $banner->price_text,
                    'button_text' => $banner->button_text,
                    'button_link' => $banner->button_link,
                    'image_path' => $banner->image_path,
                    'image_url' => asset('storage/' . $banner->image_path),
                    'type' => $banner->type,
                    'position' => $banner->position,
                    'sort_order' => $banner->sort_order,
                    'is_active' => $banner->is_active,
                    'created_at' => $banner->created_at,
                    'updated_at' => $banner->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $banners
            ]);
        } catch (\Exception $e) {
            Log::error('Banner index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching banners'
            ], 500);
        }
    }

    /**
     * POST /api/banners
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('Banner store request received', [
            'all_data' => $request->all(),
            'has_file' => $request->hasFile('image'),
            'files' => $request->allFiles()
        ]);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price_text' => 'nullable|string|max:255',
            'button_text' => 'required|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'type' => 'required|in:hero,promo',
            'position' => 'required|in:left,right,full',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
        ]);

        if ($validator->fails()) {
            Log::error('Banner validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $imagePath = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $img = Image::read($file);
                $img = $img->scaleDown(2000);

                $filename = 'banner_' . time() . '_' . Str::random(8) . '.jpg';
                $relativePath = 'banners/' . $filename;
                $absolutePath = storage_path('app/public/' . $relativePath);

                if (!is_dir(dirname($absolutePath))) {
                    mkdir(dirname($absolutePath), 0755, true);
                }

                $img->toJpeg(80)->save($absolutePath);
                $imagePath = $relativePath;
            }

            // Convert is_active to boolean
            $isActive = true;
            if ($request->has('is_active')) {
                $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            }

            $banner = Banner::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'price_text' => $request->price_text,
                'button_text' => $request->button_text,
                'button_link' => $request->button_link,
                'image_path' => $imagePath,
                'type' => $request->type,
                'position' => $request->position,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $isActive,
            ]);

            Log::info('Banner created successfully', ['banner_id' => $banner->id]);

            return response()->json([
                'success' => true,
                'message' => 'Banner created successfully',
                'data' => [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'description' => $banner->description,
                    'price_text' => $banner->price_text,
                    'button_text' => $banner->button_text,
                    'button_link' => $banner->button_link,
                    'image_path' => $banner->image_path,
                    'image_url' => asset('storage/' . $banner->image_path),
                    'type' => $banner->type,
                    'position' => $banner->position,
                    'sort_order' => $banner->sort_order,
                    'is_active' => $banner->is_active,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Banner store error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error creating banner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/banners/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price_text' => 'nullable|string|max:255',
            'button_text' => 'required|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'type' => 'required|in:hero,promo',
            'position' => 'required|in:left,right,full',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $imagePath = $banner->image_path;

            if ($request->hasFile('image')) {
                // Delete old image
                if ($banner->image_path) {
                    Storage::disk('public')->delete($banner->image_path);
                }

                $file = $request->file('image');
                $img = Image::read($file);
                $img = $img->scaleDown(2000);

                $filename = 'banner_' . time() . '_' . Str::random(8) . '.jpg';
                $relativePath = 'banners/' . $filename;
                $absolutePath = storage_path('app/public/' . $relativePath);

                if (!is_dir(dirname($absolutePath))) {
                    mkdir(dirname($absolutePath), 0755, true);
                }

                $img->toJpeg(80)->save($absolutePath);
                $imagePath = $relativePath;
            }

            $banner->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'price_text' => $request->price_text,
                'button_text' => $request->button_text,
                'button_link' => $request->button_link,
                'image_path' => $imagePath,
                'type' => $request->type,
                'position' => $request->position,
                'sort_order' => $request->sort_order ?? $banner->sort_order,
                'is_active' => $request->is_active ?? $banner->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Banner updated successfully',
                'data' => [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'description' => $banner->description,
                    'price_text' => $banner->price_text,
                    'button_text' => $banner->button_text,
                    'button_link' => $banner->button_link,
                    'image_path' => $banner->image_path,
                    'image_url' => asset('storage/' . $banner->image_path),
                    'type' => $banner->type,
                    'position' => $banner->position,
                    'sort_order' => $banner->sort_order,
                    'is_active' => $banner->is_active,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Banner update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating banner'
            ], 500);
        }
    }

    /**
     * DELETE /api/banners/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $banner = Banner::findOrFail($id);

            // Delete image file
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }

            $banner->delete();

            return response()->json([
                'success' => true,
                'message' => 'Banner deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Banner delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting banner'
            ], 500);
        }
    }
}
