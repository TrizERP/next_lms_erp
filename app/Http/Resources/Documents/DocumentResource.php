<?php

namespace App\Http\Resources\Documents;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_file_name' => $this->original_file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'current_version' => $this->current_version,
            'document_type' => $this->document_type,
            'category' => $this->category,
            'department_id' => $this->department_id,
            'department_name' => $this->department ? $this->department->department : null,
            'subject' => $this->subject,
            'document_date' => $this->document_date ? $this->document_date->format('Y-m-d') : null,
            'academic_year' => $this->academic_year,
            'organization' => $this->organization,
            'project' => $this->project,
            'lifecycle_status' => $this->lifecycle_status,
            'summary' => $this->summary,
            'confidence' => $this->confidence,
            'people' => $this->people ?: [],
            'keywords' => $this->keywords ?: [],
            'tags' => $this->tags ?: [],
            'tag_names' => $this->tag_names ?: [],
            'owner_id' => $this->owner_id,
            'owner_name' => $this->owner ? $this->owner->full_name : null,
            'visibility' => $this->visibility,
            'permissions' => $this->permissions ?: [],
            'processing_status' => $this->processing_status,
            'processing_error' => $this->processing_error,
            'warnings' => $this->warnings ?: [],
            'logical_location' => [
                'root' => 'Documents',
                'department' => $this->department ? $this->department->department : 'General',
                'document_type' => $this->document_type ?: 'Unclassified',
                'academic_year' => $this->academic_year ?: 'General',
                'subject' => $this->subject ?: 'General',
                'path' => sprintf(
                    'Documents > %s > %s > %s > %s',
                    $this->department ? $this->department->department : 'General',
                    $this->document_type ?: 'Unclassified',
                    $this->academic_year ?: 'General',
                    $this->subject ?: 'General'
                ),
            ],
            'snippet' => $this->when(isset($this->snippet), $this->snippet),
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toIso8601String() : null,
            'purge_at' => $this->deleted_at ? $this->deleted_at->copy()->addDays((int) config('idms.trash_retention_days', 30))->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
