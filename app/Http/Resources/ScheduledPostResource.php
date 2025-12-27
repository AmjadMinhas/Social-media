<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'content' => $this->content,
            'platforms' => $this->platforms,
            'platforms_list' => $this->platforms_list,
            'media' => $this->media,
            'scheduled_at' => $this->scheduled_at ? $this->scheduled_at->toIso8601String() : null,
            'published_at' => $this->published_at ? $this->published_at->toIso8601String() : null,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'error_message' => $this->error_message,
            'platform_post_ids' => $this->platform_post_ids,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}



