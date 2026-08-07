<?php

namespace App\Services\Mcp;

class McpRequestContext
{
    /**
     * @param  array<int, int>  $allowedInstituteIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly int $selectedInstituteId,
        public readonly array $allowedInstituteIds,
        public readonly ?int $userProfileId,
        public readonly ?int $clientId,
        public readonly ?int $academicYear,
        public readonly ?int $termId,
        public readonly bool $isAdmin,
        public readonly bool $isStudent
    ) {
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'role' => $this->role,
            'selected_institute_id' => $this->selectedInstituteId,
            'allowed_institute_ids' => $this->allowedInstituteIds,
            'user_profile_id' => $this->userProfileId,
            'client_id' => $this->clientId,
            'academic_year' => $this->academicYear,
            'term_id' => $this->termId,
            'is_admin' => $this->isAdmin,
            'is_student' => $this->isStudent,
        ];
    }
}
