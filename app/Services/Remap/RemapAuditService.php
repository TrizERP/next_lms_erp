<?php

namespace App\Services\Remap;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Audit writes and the rollback engine.
 *
 * Every mutation this pipeline makes is recorded per COLUMN, which is
 * what allows rollback to be one generic statement regardless of table,
 * and what allows remap:verify to prove the trail is complete by
 * comparing audit counts against a snapshot diff.
 *
 * None of the three content tables has an updated_at, so this table
 * plus the bak_ snapshot is the entire forensic record of a run.
 */
class RemapAuditService
{
    public function __construct(private string $runId)
    {
    }

    public function runId(): string
    {
        return $this->runId;
    }

    /**
     * Apply one column change and record it, inside the caller's
     * transaction.
     *
     * The UPDATE is conditional on the old value still being present,
     * so a row someone else changed concurrently is skipped rather than
     * clobbered. Returns false when nothing was written.
     */
    public function applyColumn(
        string $table,
        int $id,
        string $column,
        mixed $oldValue,
        mixed $newValue,
        string $action,
        array $meta = []
    ): bool {
        $affected = DB::table($table)
            ->where('id', $id)
            ->where(function ($q) use ($column, $oldValue) {
                $oldValue === null ? $q->whereNull($column) : $q->where($column, $oldValue);
            })
            ->update([$column => $newValue]);

        if ($affected === 0) {
            Log::channel('remap')->warning(
                "{$table}#{$id}.{$column}: expected " . var_export($oldValue, true) . ', value had changed -- skipped'
            );
            return false;
        }

        // updateOrInsert, not insertOrIgnore: a row reverted by an
        // earlier rollback and then re-applied must get a LIVE audit
        // entry again. Ignoring it would leave a real mutation with no
        // active trail, which remap:verify gate 6 correctly treats as a
        // completeness failure.
        DB::table('lms_remap_audit')->updateOrInsert([
            'run_id'      => $this->runId,
            'entity'      => $table,
            'entity_id'   => $id,
            'column_name' => $column,
        ], [
            'old_value'     => $oldValue === null ? null : (string) $oldValue,
            'new_value'     => $newValue === null ? null : (string) $newValue,
            'action'        => $action,
            'crosswalk_id'  => $meta['crosswalk_id'] ?? null,
            'confidence'    => $meta['confidence'] ?? null,
            'method'        => $meta['method'] ?? null,
            'evidence_json' => isset($meta['evidence']) ? json_encode($meta['evidence'], JSON_UNESCAPED_UNICODE) : null,
            'applied_at'    => now(),
            'reverted_at'   => null,
            'revert_note'   => null,
        ]);

        return true;
    }

    /**
     * Mirror a question chapter change into the pre-existing audit
     * table from the earlier Science cleanup, so anything built on that
     * table keeps working. Secondary log, never the source of truth.
     */
    public function mirrorQuestionFix(int $questionId, ?int $oldChapterId, ?int $newChapterId, string $action): void
    {
        try {
            DB::table('lms_question_chapter_fix')->insert([
                'question_id'     => $questionId,
                'old_chapter_id'  => $oldChapterId,
                'new_chapter_id'  => $newChapterId,
                'action'          => $action,
                'moved_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('remap')->warning("lms_question_chapter_fix mirror failed for {$questionId}: " . $e->getMessage());
        }
    }

    /**
     * Revert audited mutations, newest first.
     *
     * The NULL-safe guard on new_value means a value that someone
     * changed after the run is left alone and reported, never forced
     * back. A rollback that overwrites later human edits would be worse
     * than no rollback.
     *
     * @param  array $filters run_id, entity, crosswalk_id, legacy_chapter_id, subject_id, review_status
     * @return array{reverted:int, skipped:int, details:array}
     */
    public function rollback(array $filters, bool $apply = false): array
    {
        $query = DB::table('lms_remap_audit as a')
            ->whereNull('a.reverted_at')
            ->orderByDesc('a.id');

        if (!empty($filters['run_id'])) {
            $query->where('a.run_id', $filters['run_id']);
        }
        if (!empty($filters['entity'])) {
            $query->where('a.entity', $filters['entity']);
        }
        if (!empty($filters['crosswalk_id'])) {
            $query->where('a.crosswalk_id', $filters['crosswalk_id']);
        }

        // Selectors that live on the crosswalk rather than the audit row.
        if (!empty($filters['legacy_chapter_id']) || !empty($filters['subject_id']) || !empty($filters['review_status'])) {
            $query->join('lms_chapter_crosswalk as x', 'x.id', '=', 'a.crosswalk_id');

            if (!empty($filters['legacy_chapter_id'])) {
                $query->where('x.legacy_chapter_id', $filters['legacy_chapter_id']);
            }
            if (!empty($filters['subject_id'])) {
                $query->where('x.legacy_subject_id', $filters['subject_id']);
            }
            if (!empty($filters['review_status'])) {
                $query->where('x.review_status', $filters['review_status']);
            }
        }

        $rows = $query->get(['a.id', 'a.entity', 'a.entity_id', 'a.column_name', 'a.old_value', 'a.new_value']);

        $reverted = 0;
        $skipped  = 0;
        $details  = [];

        foreach ($rows as $row) {
            if (!$apply) {
                $details[] = "{$row->entity}#{$row->entity_id}.{$row->column_name}: {$row->new_value} -> {$row->old_value}";
                $reverted++;
                continue;
            }

            $affected = DB::table($row->entity)
                ->where('id', $row->entity_id)
                ->where(function ($q) use ($row) {
                    $row->new_value === null
                        ? $q->whereNull($row->column_name)
                        : $q->where($row->column_name, $row->new_value);
                })
                ->update([$row->column_name => $row->old_value]);

            if ($affected === 0) {
                DB::table('lms_remap_audit')->where('id', $row->id)->update([
                    'reverted_at' => now(),
                    'revert_note' => 'skipped_value_changed',
                ]);
                $skipped++;
                $details[] = "SKIPPED {$row->entity}#{$row->entity_id}.{$row->column_name} (value changed since the run)";
                continue;
            }

            DB::table('lms_remap_audit')->where('id', $row->id)->update([
                'reverted_at' => now(),
                'revert_note' => 'reverted',
            ]);
            $reverted++;
        }

        return ['reverted' => $reverted, 'skipped' => $skipped, 'details' => $details];
    }

    /**
     * Per-entity, per-column mutation counts for this run. Compared
     * against the snapshot diff by remap:verify to prove completeness.
     */
    public function counts(): array
    {
        return DB::table('lms_remap_audit')
            ->where('run_id', $this->runId)
            ->whereNull('reverted_at')
            ->groupBy('entity', 'column_name')
            ->get([
                'entity',
                'column_name',
                DB::raw('COUNT(*) as n'),
            ])
            ->map(fn ($r) => ['entity' => $r->entity, 'column' => $r->column_name, 'n' => (int) $r->n])
            ->all();
    }
}
