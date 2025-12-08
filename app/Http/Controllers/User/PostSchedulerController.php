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
        $organizationId = session()->get('current_organization');
        
        if ($uuid == null) {
            // List all scheduled posts
            $searchTerm = $request->query('search');
            $status = $request->query('status');
            $platform = $request->query('platform');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            
            $query = ScheduledPost::where('organization_id', $organizationId)
                ->where('deleted_at', null);
            
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
                $platforms = explode(',', $platform);
                $query->where(function ($q) use ($platforms) {
                    foreach ($platforms as $plat) {
                        $q->orWhereJsonContains('platforms', trim($plat));
                    }
                });
            }
            
            if ($dateFrom) {
                $query->whereDate('scheduled_at', '>=', $dateFrom);
            }
            
            if ($dateTo) {
                $query->whereDate('scheduled_at', '<=', $dateTo);
            }
            
            $paginated = $query->latest('scheduled_at')->paginate(10);
            
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
                });
            
            return Inertia::render('User/PostScheduler/Index', [
                'title' => __('Post Scheduler'),
                'allowCreate' => true,
                'rows' => $rows,
                'filters' => request()->all(['search', 'status', 'platform', 'date_from', 'date_to']),
                'connectedAccounts' => $connectedAccounts
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
                'post' => $post
            ]);
        }
    }

    public function store(Request $request)
    {
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
            'media.*' => 'string'
        ]);

        $organizationId = session()->get('current_organization');

        // Determine scheduled_at based on publish_type
        $scheduledAt = now();
        if ($validated['publish_type'] === 'scheduled') {
            $scheduledAt = $validated['scheduled_at'];
        } elseif ($validated['publish_type'] === 'time_range') {
            // For time_range, we'll calculate a random time when processing
            // For now, set to scheduled_from
            $scheduledAt = $validated['scheduled_from'];
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
            'scheduled_from' => $validated['scheduled_from'] ?? null,
            'scheduled_to' => $validated['scheduled_to'] ?? null,
            'media' => isset($validated['media']) ? json_encode($validated['media']) : null,
            'status' => $validated['publish_type'] === 'now' ? 'scheduled' : 'scheduled'
        ]);

        // If publish_type is 'now', dispatch immediately
        if ($validated['publish_type'] === 'now') {
            \App\Jobs\PublishScheduledPostJob::dispatch($post);
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
}

