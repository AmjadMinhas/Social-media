<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\ScheduledPostResource;
use App\Models\Organization;
use App\Models\ScheduledPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Illuminate\Support\Str;

class PostSchedulerController extends BaseController
{
    public function index(Request $request, $uuid = null)
    {
        try {
            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('scheduled_posts')) {
                \Log::error('scheduled_posts table does not exist');
                return Redirect::route('dashboard')->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Database table not found. Please run migrations.')
                    ]
                );
            }
            
            $organizationId = session()->get('current_organization');
            
            if (!$organizationId) {
                return Redirect::route('dashboard')->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Please select an organization first')
                    ]
                );
            }
            
            if ($uuid == null) {
            // List all scheduled posts
            $searchTerm = $request->query('search');
            $status = $request->query('status');
            $platform = $request->query('platform');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            
            $query = ScheduledPost::where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->with('user'); // Eager load user to avoid N+1 queries
            
            if ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', '%' . $searchTerm . '%')
                      ->orWhere('content', 'like', '%' . $searchTerm . '%');
                });
            }
            
            if ($status) {
                $query->where('status', $status);
            }
            
            if ($platform) {
                $platforms = array_filter(array_map('trim', explode(',', $platform)));
                if (!empty($platforms)) {
                    \Log::info('Filtering by platforms', [
                        'platforms' => $platforms,
                        'platform_string' => $platform,
                        'organization_id' => $organizationId
                    ]);
                    
                    // Filter posts that have at least one of the selected platforms
                    // The platforms column stores JSON array like ["twitter", "linkedin"]
                    $query->where(function ($q) use ($platforms) {
                        $first = true;
                        foreach ($platforms as $plat) {
                            $normalizedPlat = strtolower(trim($plat));
                            
                            \Log::info('Applying platform filter', [
                                'platform' => $plat,
                                'normalized' => $normalizedPlat,
                                'is_first' => $first
                            ]);
                            
                            // The database stores platforms as JSON string: ["twitter"]
                            // Simple LIKE search for the platform name (works regardless of quotes/formatting)
                            
                            if ($first) {
                                // Just search for the platform name - simplest and most reliable
                                $q->whereRaw('platforms LIKE ?', ['%' . $normalizedPlat . '%']);
                                $first = false;
                            } else {
                                $q->orWhereRaw('platforms LIKE ?', ['%' . $normalizedPlat . '%']);
                            }
                        }
                    });
                    
                    // Debug: Get a sample post to see actual platform format
                    $samplePost = ScheduledPost::where('organization_id', $organizationId)
                        ->whereNull('deleted_at')
                        ->first();
                    
                    if ($samplePost) {
                        \Log::info('Sample post platforms for debugging', [
                            'post_id' => $samplePost->id,
                            'platforms_raw' => $samplePost->getRawOriginal('platforms'),
                            'platforms_casted' => $samplePost->platforms,
                            'platforms_type' => gettype($samplePost->platforms),
                            'is_array' => is_array($samplePost->platforms),
                        ]);
                    }
                    
                    \Log::info('Platform filter applied', [
                        'platforms' => $platforms,
                        'query_sql' => $query->toSql(),
                        'query_bindings' => $query->getBindings()
                    ]);
                }
            }
            
            if ($dateFrom) {
                try {
                    $query->whereDate('scheduled_at', '>=', $dateFrom);
                } catch (\Exception $e) {
                    \Log::warning('Invalid date_from filter: ' . $dateFrom);
                }
            }
            
            if ($dateTo) {
                try {
                    $query->whereDate('scheduled_at', '<=', $dateTo);
                } catch (\Exception $e) {
                    \Log::warning('Invalid date_to filter: ' . $dateTo);
                }
            }
            
            // Debug: Log the query before execution
            \Log::info('Post scheduler query before execution', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'platform_filter' => $platform ?? null,
                'status_filter' => $status ?? null,
                'search_filter' => $searchTerm ?? null,
            ]);
            
            // Order by created_at for "now" posts, scheduled_at for scheduled posts
            $paginated = $query->orderByRaw('CASE WHEN publish_type = "now" THEN created_at ELSE scheduled_at END DESC')
                ->paginate(10);
            
            // Debug: Log results and sample data
            \Log::info('Post scheduler query results', [
                'total' => $paginated->total(),
                'count' => $paginated->count(),
                'platform_filter_applied' => !empty($platform),
                'sample_platforms' => $paginated->items() ? collect($paginated->items())->take(3)->map(function($post) {
                    return [
                        'id' => $post->id,
                        'platforms' => $post->platforms,
                        'platforms_type' => gettype($post->platforms)
                    ];
                })->toArray() : []
            ]);
            
            // Transform paginated data
            $rows = [
                'data' => ScheduledPostResource::collection($paginated->items())->resolve(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'links' => $paginated->linkCollection()->toArray(),
            ];
            
            // Get connected social accounts for platform filters
            $connectedAccounts = \App\Models\SocialAccount::where('organization_id', $organizationId)
                ->where('is_active', true)
                ->get()
                ->groupBy('platform')
                ->map(function ($accounts) {
                    return $accounts->first();
                })
                ->toArray(); // Convert to array for Inertia
            
            return Inertia::render('User/PostScheduler/Index', [
                'title' => __('Post Scheduler'),
                'allowCreate' => true,
                'rows' => $rows,
                'filters' => request()->all(['search', 'status', 'platform', 'date_from', 'date_to']),
                'connectedAccounts' => $connectedAccounts ?: []
            ]);
            
        } else if ($uuid == 'create') {
            // Show create form
            return Inertia::render('User/PostScheduler/Create', [
                'title' => __('Schedule New Post')
            ]);
            
        } else {
            // Show single scheduled post
            $post = ScheduledPost::where('uuid', $uuid)
                ->where('organization_id', $organizationId)
                ->firstOrFail();
            
            return Inertia::render('User/PostScheduler/View', [
                'title' => __('View Scheduled Post'),
                'post' => (new ScheduledPostResource($post))->resolve()
            ]);
            }
        } catch (\Exception $e) {
            \Log::error('PostSchedulerController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return Redirect::back()->with(
                'status', [
                    'type' => 'error',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]
            );
        }
    }

    public function store(Request $request)
    {
        // Simple log first to ensure it works
        error_log('=== POST SCHEDULER STORE CALLED ===');
        error_log('Request method: ' . $request->method());
        error_log('All data: ' . json_encode($request->all()));
        error_log('Title: ' . ($request->input('title') ?? 'NULL'));
        error_log('Content: ' . ($request->input('content') ?? 'NULL'));
        error_log('Platforms: ' . json_encode($request->input('platforms')));
        error_log('Publish type: ' . ($request->input('publish_type') ?? 'NULL'));
        
        // Log all incoming request data for debugging
        \Illuminate\Support\Facades\Log::info('Post scheduler store request received', [
            'all_request_data' => $request->all(),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'platforms' => $request->input('platforms'),
            'publish_type' => $request->input('publish_type'),
            'scheduled_at' => $request->input('scheduled_at'),
            'media' => $request->input('media'),
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'is_json' => $request->isJson(),
            'is_ajax' => $request->ajax(),
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:facebook,instagram,tiktok,twitter,linkedin',
            'publish_type' => 'required|in:now,scheduled,time_range',
            'scheduled_at' => 'required_if:publish_type,scheduled|nullable|date|after:now',
            'scheduled_from' => 'required_if:publish_type,time_range|nullable|date|after:now',
            'scheduled_to' => 'required_if:publish_type,time_range|nullable|date|after:scheduled_from',
            'media' => 'nullable|array',
            // Media can be either strings (legacy) or objects with url, thumbnail, is_video
            'media.*' => 'nullable'
        ]);

        $organizationId = session()->get('current_organization');

        // Determine scheduled_at based on publish_type
        // Note: scheduled_at comes from frontend in local timezone, we store it as-is
        // The frontend datetime-local input sends time in user's local timezone
        $scheduledAt = now();
        if ($validated['publish_type'] === 'scheduled') {
            // Parse the datetime string - it's already in the user's local timezone
            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
        } elseif ($validated['publish_type'] === 'time_range') {
            // For time_range, we'll calculate a random time when processing
            // For now, set to scheduled_from
            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_from']);
        }

        $post = ScheduledPost::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'platforms' => json_encode($validated['platforms']),
            'publish_type' => $validated['publish_type'],
            'scheduled_at' => $scheduledAt,
            'scheduled_from' => isset($validated['scheduled_from']) ? \Carbon\Carbon::parse($validated['scheduled_from']) : null,
            'scheduled_to' => isset($validated['scheduled_to']) ? \Carbon\Carbon::parse($validated['scheduled_to']) : null,
            'media' => isset($validated['media']) && !empty($validated['media']) && is_array($validated['media']) 
                ? json_encode(array_map(function($item) {
                    // Handle both object format {url, thumbnail} and string format
                    if (is_array($item) && isset($item['url'])) {
                        return $item; // Already in object format
                    }
                    return is_string($item) ? ['url' => $item, 'thumbnail' => null, 'is_video' => false] : $item;
                }, $validated['media'])) 
                : null,
            'status' => 'scheduled'
        ]);

        // Log after creation to verify media was saved
        \Illuminate\Support\Facades\Log::info('Post created', [
            'post_id' => $post->id,
            'post_uuid' => $post->uuid,
            'raw_media' => $post->getRawOriginal('media'),
            'decoded_media' => $post->media,
            'has_media' => !empty($post->media),
            'scheduled_at' => $post->scheduled_at
        ]);

        // If publish_type is 'now', publish immediately (synchronously)
        if ($validated['publish_type'] === 'now') {
            \Illuminate\Support\Facades\Log::info('Publishing post immediately', [
                'post_id' => $post->id,
                'post_uuid' => $post->uuid,
                'platforms' => $validated['platforms'],
                'has_media' => !empty($validated['media']),
                'media_count' => isset($validated['media']) ? count($validated['media']) : 0,
                'media' => $validated['media'] ?? null
            ]);
            
            // Mark as processing
            $post->update(['status' => 'publishing']);
            
            try {
                // Use dispatchSync to execute immediately (no queue worker needed)
                \App\Jobs\PublishScheduledPostJob::dispatchSync($post);
                
                \Illuminate\Support\Facades\Log::info('Post publishing job executed synchronously', [
                    'post_id' => $post->id
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to publish post', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Update post status to failed
                $post->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to publish post: ' . $e->getMessage()
                ]);
            }
        }

        $message = $validated['publish_type'] === 'now' 
            ? __('Post published successfully!')
            : __('Post scheduled successfully!');

        return Redirect::route('post-scheduler')->with(
            'status', [
                'type' => 'success',
                'message' => $message
            ]
        );
    }

    public function update(Request $request, $uuid)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:facebook,instagram,tiktok,twitter,linkedin',
            'publish_type' => 'required|in:now,scheduled,time_range',
            'scheduled_at' => 'required_if:publish_type,scheduled|nullable|date',
            'scheduled_from' => 'required_if:publish_type,time_range|nullable|date',
            'scheduled_to' => 'required_if:publish_type,time_range|nullable|date|after:scheduled_from',
            'media' => 'nullable|array',
            'status' => 'nullable|in:scheduled,published,failed,cancelled'
        ]);

        $organizationId = session()->get('current_organization');

        $post = ScheduledPost::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Determine scheduled_at based on publish_type
        $scheduledAt = $post->scheduled_at;
        if ($validated['publish_type'] === 'scheduled' && isset($validated['scheduled_at'])) {
            $scheduledAt = $validated['scheduled_at'];
        } elseif ($validated['publish_type'] === 'time_range' && isset($validated['scheduled_from'])) {
            $scheduledAt = $validated['scheduled_from'];
        } elseif ($validated['publish_type'] === 'now') {
            $scheduledAt = now();
        }

        $post->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'platforms' => json_encode($validated['platforms']),
            'publish_type' => $validated['publish_type'],
            'scheduled_at' => $scheduledAt,
            'scheduled_from' => $validated['scheduled_from'] ?? null,
            'scheduled_to' => $validated['scheduled_to'] ?? null,
            'media' => isset($validated['media']) ? json_encode($validated['media']) : null,
            'status' => $validated['status'] ?? $post->status
        ]);

        // If publish_type is 'now' and status is still scheduled, dispatch immediately
        if ($validated['publish_type'] === 'now' && $post->status === 'scheduled') {
            \App\Jobs\PublishScheduledPostJob::dispatch($post);
        }

        return Redirect::route('post-scheduler')->with(
            'status', [
                'type' => 'success',
                'message' => __('Post updated successfully!')
            ]
        );
    }

    public function delete($uuid)
    {
        $organizationId = session()->get('current_organization');

        $post = ScheduledPost::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $post->update(['deleted_at' => now()]);

        return Redirect::back()->with(
            'status', [
                'type' => 'success',
                'message' => __('Scheduled post deleted successfully!')
            ]
        );
    }

    /**
     * Upload media for post scheduler
     */
    public function uploadMedia(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi|max:102400', // 100MB max for videos
            ]);

            $organizationId = session()->get('current_organization');
            
            if (!$organizationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization not found in session'
                ], 400);
            }

            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            
            \Illuminate\Support\Facades\Log::info('Post scheduler media upload started', [
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'organization_id' => $organizationId
            ]);
            
            // Get storage system
            $storage = \App\Models\Setting::where('key', 'storage_system')->first();
            $storageSystem = $storage ? $storage->value : 'local';

            if ($storageSystem === 'local') {
                $filePath = \Illuminate\Support\Facades\Storage::disk('local')->put('public/post-scheduler', $file);
                // FileController expects path relative to storage/app, so use the full path
                $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($filePath, '/');
            } else if ($storageSystem === 'aws') {
                $uploadedFile = $file->store('uploads/post-scheduler/' . $organizationId, 's3');
                $mediaUrl = \Illuminate\Support\Facades\Storage::disk('s3')->url($uploadedFile);
                $filePath = $uploadedFile;
            } else {
                $filePath = \Illuminate\Support\Facades\Storage::disk('local')->put('public/post-scheduler', $file);
                // FileController expects path relative to storage/app, so use the full path
                $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($filePath, '/');
            }

            // Generate thumbnail for videos
            $thumbnailUrl = null;
            $fileExtension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            $isVideo = in_array($fileExtension, ['mp4', 'mov', 'avi']) || str_starts_with($mimeType, 'video/');
            
            if ($isVideo) {
                try {
                    $thumbnailUrl = \App\Services\ThumbnailService::generateThumbnail($file, $filePath, $storageSystem);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to generate video thumbnail: ' . $e->getMessage());
                    // Continue without thumbnail
                }
            }

            \Illuminate\Support\Facades\Log::info('Post scheduler media upload successful', [
                'media_url' => $mediaUrl,
                'thumbnail_url' => $thumbnailUrl,
                'is_video' => $isVideo
            ]);

            return response()->json([
                'success' => true,
                'url' => $mediaUrl,
                'path' => $mediaUrl,
                'name' => $fileName,
                'thumbnail' => $thumbnailUrl,
                'is_video' => $isVideo
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Post scheduler media upload validation error', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Post scheduler media upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload media: ' . $e->getMessage()
            ], 500);
        }
    }
}

