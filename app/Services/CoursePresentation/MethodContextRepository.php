<?php

namespace App\Services\CoursePresentation;

use App\Models\PedagogicalMethodContext;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the stored context of a teaching method. Read only. Returns null when the table is not there yet (the migration
 * is applied by hand), when the method has no row, or when the row is not complete, so a caller can fall back safely;
 * `isActive()` tells an administrator's "off" apart from "not stored".
 */
class MethodContextRepository
{
    /** @var array<string,?PedagogicalMethodContext> */
    private array $rows = [];
    private ?bool $tableExists = null;

    public function row(string $key): ?PedagogicalMethodContext
    {
        if (array_key_exists($key, $this->rows)) {
            return $this->rows[$key];
        }
        try {
            $this->tableExists ??= Schema::hasTable('pedagogical_method_contexts');
            $row = $this->tableExists ? PedagogicalMethodContext::query()->where('method_key', $key)->first() : null;
        } catch (\Throwable) {
            $row = null;
        }

        return $this->rows[$key] = ($row !== null && $this->complete($row)) ? $row : null;
    }

    /** The row when the method is stored AND switched on; null otherwise. */
    public function active(string $key): ?PedagogicalMethodContext
    {
        $row = $this->row($key);

        return $row !== null && $row->is_active ? $row : null;
    }

    /** True only for a stored method an administrator switched off. */
    public function isDisabled(string $key): bool
    {
        $row = $this->row($key);

        return $row !== null && !$row->is_active;
    }

    private function complete(PedagogicalMethodContext $row): bool
    {
        foreach (PedagogicalMethodContext::CONTEXT_FIELDS as $field) {
            if (empty($row->{$field})) {
                return false;
            }
        }

        return is_array($row->workflow_stages) && $row->workflow_stages !== [];
    }
}
