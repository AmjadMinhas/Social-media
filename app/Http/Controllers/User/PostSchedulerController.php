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
            'scheduled_at' => 'required|date|after:now',
            'media' => 'nullable|array',
            'media.*' => 'string'
        ]);

        $organizationId = session()->get('current_organization');

        ScheduledPost::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'platforms' => json_encode($validated['platforms']),
            'scheduled_at' => $validated['scheduled_at'],
            'media' => isset($validated['media']) ? json_encode($validated['media']) : null,
            'status' => 'scheduled'
        ]);

        return Redirect::route('post-scheduler')->with(
            'status', [
                'type' => 'success',
                'message' => __('Post scheduled successfully!')
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
            'scheduled_at' => 'required|date',
            'media' => 'nullable|array',
            'status' => 'nullable|in:scheduled,published,failed,cancelled'
        ]);

        $organizationId = session()->get('current_organization');

        $post = ScheduledPost::where('uuid', $uuid)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $post->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'platforms' => json_encode($validated['platforms']),
            'scheduled_at' => $validated['scheduled_at'],
            'media' => isset($validated['media']) ? json_encode($validated['media']) : null,
            'status' => $validated['status'] ?? $post->status
        ]);

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

