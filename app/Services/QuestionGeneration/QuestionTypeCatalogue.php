<?php

namespace App\Services\QuestionGeneration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only view of `question_type_catalog`, one entry per code.
 *
 * The catalogue holds one row per (code, publisher) -- `case_study` appears four
 * times, once per publisher that uses it. The generator only ever selects a CODE
 * (the publisher plays no part in a format or its H5P mapping), so rows collapse
 * to one per code, preferring the standard, publisher-less row and then the
 * lowest id: the same row ApiLmsCourseController::getQuestionBank's label join
 * picks.
 *
 * Only active rows (status = 1) are read. The table is global, not tenant data.
 */
class QuestionTypeCatalogue
{
    /** @var array<string, array{code: string, label: string, lms_question_type_id: ?int, default_marks: ?int, is_standard: int}>|null */
    private ?array $entries = null;

    /** @return array<string, array{code: string, label: string, lms_question_type_id: ?int, default_marks: ?int, is_standard: int}> */
    public function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $this->entries = [];

        if (!Schema::hasTable('question_type_catalog')) {
            return $this->entries;
        }

        $rows = DB::table('question_type_catalog')
            ->where('status', 1)
            ->orderByDesc('is_standard')
            ->orderByRaw('publisher_id IS NULL DESC')
            ->orderBy('id')
            ->get(['code', 'label', 'lms_question_type_id', 'default_marks', 'is_standard']);

        foreach ($rows as $row) {
            $code = (string) $row->code;
            if ($code === '' || isset($this->entries[$code])) {
                continue;
            }

            $this->entries[$code] = [
                'code' => $code,
                'label' => (string) $row->label,
                'lms_question_type_id' => $row->lms_question_type_id !== null ? (int) $row->lms_question_type_id : null,
                'default_marks' => $row->default_marks !== null ? (int) $row->default_marks : null,
                'is_standard' => (int) $row->is_standard,
            ];
        }

        return $this->entries;
    }

    /** @return array{code: string, label: string, lms_question_type_id: ?int, default_marks: ?int, is_standard: int}|null */
    public function find(string $code): ?array
    {
        return $this->entries()[$code] ?? null;
    }
}
