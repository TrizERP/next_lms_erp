<?php

namespace App\Policies;

use App\Models\Documents\DocumentMaster;
use App\Models\user\tbluserModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Check if user is an Administrator
     */
    protected function isAdmin($user): bool
    {
        if (!$user) return false;
        if (in_array((int)($user->is_admin ?? 0), [1, 2], true)) return true;
        $profile = strtolower(trim((string)($user->profile_name ?? $user->user_profile_name ?? '')));
        return in_array($profile, ['admin', 'super admin', 'principal', 'school admin'], true);
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view($user, DocumentMaster $document): bool
    {
        if ($this->isAdmin($user)) return true;

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        if ($document->owner_id == $userId || $document->created_by == $userId) return true;
        if ($document->visibility === 'organization') return true;

        $deptId = is_object($user) ? ($user->department_id ?? null) : null;
        if ($document->visibility === 'department' && $deptId && $document->department_id == $deptId) {
            return true;
        }

        // Check custom view_principals
        $profileId = is_object($user) ? ($user->user_profile_id ?? null) : null;
        $principals = ["user:{$userId}"];
        if ($profileId) $principals[] = "role:{$profileId}";
        if ($deptId) $principals[] = "dept:{$deptId}";

        $docPrincipals = $document->view_principals ?: [];
        if (array_intersect($principals, $docPrincipals)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can edit the document metadata / tags.
     */
    public function update($user, DocumentMaster $document): bool
    {
        if ($this->isAdmin($user)) return true;

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        if ($document->owner_id == $userId || $document->created_by == $userId) return true;

        // Check explicit permissions JSON
        return $this->hasPermission($user, $document, 'edit');
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download($user, DocumentMaster $document): bool
    {
        if ($this->isAdmin($user)) return true;
        if (!$this->view($user, $document)) return false;

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        if ($document->owner_id == $userId || $document->created_by == $userId) return true;

        // Check permissions JSON for download flag (default true if user has view access)
        return $this->hasPermission($user, $document, 'download', true);
    }

    /**
     * Determine whether the user can share the document.
     */
    public function share($user, DocumentMaster $document): bool
    {
        if ($this->isAdmin($user)) return true;

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        if ($document->owner_id == $userId || $document->created_by == $userId) return true;

        return $this->hasPermission($user, $document, 'share', false);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete($user, DocumentMaster $document): bool
    {
        if ($this->isAdmin($user)) return true;

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        return $document->owner_id == $userId || $document->created_by == $userId;
    }

    /**
     * Check custom permissions JSON entries
     */
    protected function hasPermission($user, DocumentMaster $document, string $field, bool $default = false): bool
    {
        $permissions = $document->permissions;
        if (empty($permissions) || !is_array($permissions)) {
            return $default;
        }

        $userId = is_numeric($user) ? (int)$user : ($user->id ?? 0);
        $profileId = is_object($user) ? ($user->user_profile_id ?? null) : null;
        $deptId = is_object($user) ? ($user->department_id ?? null) : null;

        foreach ($permissions as $perm) {
            $type = $perm['type'] ?? '';
            $id = (int)($perm['id'] ?? 0);

            $match = false;
            if ($type === 'user' && $id === $userId) $match = true;
            elseif ($type === 'role' && $profileId && $id === (int)$profileId) $match = true;
            elseif ($type === 'department' && $deptId && $id === (int)$deptId) $match = true;

            if ($match && isset($perm[$field])) {
                return (bool)$perm[$field];
            }
        }

        return $default;
    }
}
