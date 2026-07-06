<?php
// app/Http/Controllers/ResourceController.php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ResourceController extends Controller
{
    /**
     * Allowed file types
     */
    protected $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/mp4',
        'video/avi',
        'video/mov',
        'audio/mpeg',
        'audio/wav',
        'application/zip',
        'application/x-rar-compressed',
        'text/plain',
        'text/csv',
    ];

    /**
     * Max file size: 50MB
     */
    protected $maxFileSize = 50 * 1024 * 1024;

    /**
     * Get paginated list of resources with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Resource::with(['uploadedBy', 'approvedBy']);

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('category', 'LIKE', "%{$search}%")
                  ->orWhereHas('uploadedBy', function ($sub) use ($search) {
                      $sub->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Paginate
        $perPage = $request->get('limit', 10);
        $page = $request->get('page', 1);
        
        $resources = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Resources retrieved successfully.',
            'data' => [
                'items' => $resources->items(),
                'total' => $resources->total(),
                'page' => $resources->currentPage(),
                'limit' => $resources->perPage(),
                'totalPages' => $resources->lastPage(),
            ],
        ]);
    }

    /**
     * Upload a new resource
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in([
                'Presentation',
                'Seminar Paper',
                'Research Material',
                'Abstract',
                'Event Material',
                'Workshop Guide',
                'Video Recording',
                'Audio Recording',
                'Data Set',
                'Other',
            ])],
            'file' => [
                'required',
                'file',
                "max:{$this->maxFileSize}",
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,mp4,avi,mov,mp3,wav,zip,rar,txt,csv',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $fileType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $filePath = $file->storeAs('resources', $fileName, 'public');

            if (!$filePath) {
                throw new \Exception('Failed to store file.');
            }

            $resource = Resource::create([
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'file_path' => $filePath,
                'file_name' => $originalName,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'status' => 'pending',
                'uploaded_by' => auth()->id(),
                'downloads' => 0,
            ]);

            // Load relationships
            $resource->load(['uploadedBy', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Resource uploaded successfully. It will be reviewed by an admin.',
                'data' => $resource,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload resource: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific resource
     */
    public function show($id): JsonResponse
    {
        $resource = Resource::with(['uploadedBy', 'approvedBy'])->find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Resource retrieved successfully.',
            'data' => $resource,
        ]);
    }

    /**
     * Download a resource (increments download count)
     */
    public function download($id): JsonResponse
    {
        $resource = Resource::find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        // Check if resource is approved for download
        if (!$resource->canBeDownloaded()) {
            return response()->json([
                'success' => false,
                'message' => 'This resource is not available for download.',
            ], 403);
        }

        try {
            // Check if file exists
            if (!Storage::disk('public')->exists($resource->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource file not found.',
                ], 404);
            }

            // Increment download count
            $resource->incrementDownloads();

            // Get file
            $file = Storage::disk('public')->get($resource->file_path);
            $headers = [
                'Content-Type' => $resource->file_type,
                'Content-Disposition' => 'attachment; filename="' . $resource->file_name . '"',
            ];

            return response()->make($file, 200, $headers);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download resource: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a resource (Admin only)
     */
    public function approve($id): JsonResponse
    {
        $resource = Resource::find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($resource->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Resource is already approved.',
            ], 400);
        }

        try {
            $resource->approve();
            $resource->load(['uploadedBy', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Resource approved successfully.',
                'data' => $resource,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve resource: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a resource (Admin only)
     */
    public function reject($id): JsonResponse
    {
        $resource = Resource::find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($resource->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Resource is already rejected.',
            ], 400);
        }

        try {
            $resource->reject();
            $resource->load(['uploadedBy', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Resource rejected successfully.',
                'data' => $resource,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject resource: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a resource
     */
    public function destroy($id): JsonResponse
    {
        $resource = Resource::find($id);

        if (!$resource) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        // Check if user is authorized (admin or resource owner)
        if (auth()->id() !== $resource->uploaded_by && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this resource.',
            ], 403);
        }

        try {
            // Delete the file from storage
            if (Storage::disk('public')->exists($resource->file_path)) {
                Storage::disk('public')->delete($resource->file_path);
            }

            $resource->delete();

            return response()->json([
                'success' => true,
                'message' => 'Resource deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete resource: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all categories (for filter dropdown)
     */
    public function categories(): JsonResponse
    {
        $categories = [
            'Presentation',
            'Seminar Paper',
            'Research Material',
            'Abstract',
            'Event Material',
            'Workshop Guide',
            'Video Recording',
            'Audio Recording',
            'Data Set',
            'Other',
        ];

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    /**
     * Get statistics about resources
     */
    public function stats(): JsonResponse
    {
        $total = Resource::count();
        $pending = Resource::pending()->count();
        $approved = Resource::approved()->count();
        $rejected = Resource::rejected()->count();
        $totalDownloads = Resource::sum('downloads');

        return response()->json([
            'success' => true,
            'message' => 'Statistics retrieved successfully.',
            'data' => [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'totalDownloads' => $totalDownloads,
            ],
        ]);
    }
}