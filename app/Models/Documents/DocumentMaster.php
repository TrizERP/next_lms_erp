<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\HrmsDepartment;
use App\Models\user\tbluserModel;

class DocumentMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'document_master';

    protected $fillable = [
        'sub_institute_id',
        'title',
        'original_file_name',
        'mime_type',
        'size',
        'checksum_sha256',
        'storage_path',
        'preview_path',
        'current_version',
        'document_type',
        'category',
        'department_id',
        'subject',
        'document_date',
        'academic_year',
        'organization',
        'project',
        'lifecycle_status',
        'summary',
        'confidence',
        'people',
        'keywords',
        'extracted_text',
        'tags_text',
        'tags',
        'tag_names',
        'embedding',
        'owner_id',
        'visibility',
        'view_principals',
        'permissions',
        'processing_status',
        'processing_error',
        'warnings',
        'created_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'current_version' => 'integer',
        'confidence' => 'float',
        'document_date' => 'date:Y-m-d',
        'people' => 'array',
        'keywords' => 'array',
        'tags' => 'array',
        'tag_names' => 'array',
        'view_principals' => 'array',
        'permissions' => 'array',
        'warnings' => 'array',
    ];

    /**
     * Scope visibleTo: Reusable Eloquent scope applied to every query.
     * Admin bypass OR Owner OR Visibility Rule OR JSON_OVERLAPS(view_principals, user's principals).
     */
    public function scopeVisibleTo(Builder $query, $user, ?int $subInstituteId = null): Builder
    {
        if ($subInstituteId) {
            $query->where('document_master.sub_institute_id', $subInstituteId);
        }

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        $isAdmin = false;
        $profileId = 0;
        $deptId = null;

        if (is_object($user)) {
            $isAdmin = in_array((int)($user->is_admin ?? 0), [1, 2], true);
            $profileId = (int)($user->user_profile_id ?? 0);
            $deptId = $user->department_id ? (int)$user->department_id : null;
        }

        // Admin bypass
        if ($isAdmin) {
            return $query;
        }

        // Compute principals for user
        $principals = ["user:{$userId}"];
        if ($profileId) {
            $principals[] = "role:{$profileId}";
        }
        if ($deptId) {
            $principals[] = "dept:{$deptId}";
        }

        $jsonPrincipals = json_encode($principals);

        return $query->where(function (Builder $q) use ($userId, $deptId, $jsonPrincipals) {
            // 1. Owner
            $q->where('document_master.owner_id', $userId)
              ->orWhere('document_master.created_by', $userId)
              // 2. Organization visibility
              ->orWhere('document_master.visibility', 'organization');

            // 3. Department visibility
            if ($deptId) {
                $q->orWhere(function (Builder $sub) use ($deptId) {
                    $sub->where('document_master.visibility', 'department')
                        ->where('document_master.department_id', $deptId);
                });
            }

            // 4. view_principals overlap check
            $q->orWhereRaw('JSON_OVERLAPS(document_master.view_principals, ?)', [$jsonPrincipals]);
        });
    }

    /**
     * Recompute view_principals from visibility, department, owner and permission overrides.
     */
    public function recomputeViewPrincipals(): array
    {
        $principals = [];

        // Always owner
        if ($this->owner_id) {
            $principals[] = "user:{$this->owner_id}";
        }

        if ($this->visibility === 'department' && $this->department_id) {
            $principals[] = "dept:{$this->department_id}";
        }

        if (is_array($this->permissions)) {
            foreach ($this->permissions as $perm) {
                if (!empty($perm['view'])) {
                    $type = $perm['type'] ?? '';
                    $targetId = $perm['id'] ?? 0;
                    if ($type === 'user') {
                        $principals[] = "user:{$targetId}";
                    } elseif ($type === 'role') {
                        $principals[] = "role:{$targetId}";
                    } elseif ($type === 'department') {
                        $principals[] = "dept:{$targetId}";
                    }
                }
            }
        }

        $unique = array_values(array_unique($principals));
        $this->view_principals = $unique;
        return $unique;
    }

    /**
     * Synchronize tags JSON, tag_names lowercase array, and tags_text fulltext column.
     */
    public function syncTags(array $tags): void
    {
        $this->tags = $tags;
        $acceptedNames = [];

        foreach ($tags as $tag) {
            $name = trim($tag['name'] ?? '');
            $status = $tag['status'] ?? 'suggested';
            if ($name !== '' && $status === 'accepted') {
                $acceptedNames[] = mb_strtolower($name);
            }
        }

        $acceptedNames = array_values(array_unique($acceptedNames));
        $this->tag_names = $acceptedNames;
        $this->tags_text = implode(' ', $acceptedNames);
    }

    /**
     * Relationships
     */
    public function department()
    {
        return $this->belongsTo(HrmsDepartment::class, 'department_id');
    }

    public function owner()
    {
        return $this->belongsTo(tbluserModel::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(tbluserModel::class, 'created_by');
    }

    public function history()
    {
        return $this->hasMany(DocumentHistory::class, 'document_id')->orderBy('created_at', 'desc');
    }

    public function versions()
    {
        return $this->hasMany(DocumentHistory::class, 'document_id')
            ->where('entry_type', 'version')
            ->orderBy('version_number', 'desc');
    }
}
