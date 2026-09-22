<?php

namespace App\Services\PAL\Coherence;

use App\Models\PAL\LearningRelation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every change a person makes to a prerequisite edge goes through here.
 *
 * ---------------------------------------------------------------------------
 * WHY THE API TALKS ABOUT PREREQUISITE AND DEPENDENT, NOT SOURCE AND TARGET
 * ---------------------------------------------------------------------------
 * Three directions meet at this class and two of them disagree:
 *   - STORAGE  (pal_concept_relations): from = dependent, to = prerequisite.
 *   - RENDERED (CurriculumGraphBuilder): source = prerequisite, target = dependent,
 *     because arrows follow the order things are learned.
 * A caller passing "source" would be guessing which of the two it meant, and a
 * wrong guess inverts a curriculum rule silently - nothing errors, the map simply
 * teaches backwards. So the write API names the roles instead: `prerequisite_id`
 * is what must be learned first, `dependent_id` is what it unlocks. Neither can be
 * read the wrong way round.
 *
 * ---------------------------------------------------------------------------
 * WHICH TABLE AN EDGE LANDS IN
 * ---------------------------------------------------------------------------
 * concept -> concept goes to `pal_concept_relations`, because six shipped services
 * read the concept graph there - the ESO D2 prerequisite gate among them. Anything
 * else goes to `pal_learning_relations`. A mixed pair (concept -> topic) is refused
 * rather than guessed: the two levels are not interchangeable and silently picking
 * one would split the graph.
 *
 * ---------------------------------------------------------------------------
 * CYCLES WARN, THEY DO NOT BLOCK
 * ---------------------------------------------------------------------------
 * The live graph already contains 41 reciprocal `requires` pairs (measured
 * 2026-08-24). Refusing to create a cycle would make that existing data uneditable -
 * a curator could not fix a reciprocal pair without first being allowed to touch it.
 * So a cycle-forming edge is created and reported in `warnings`, and the map flags
 * the ring. This is a deliberate choice, not an oversight.
 */
class RelationWriter
{
    /** Node kinds a caller may name. */
    private const TYPES = ['concept', 'topic', 'unit', 'chapter'];

    /** Statuses a human review may set. */
    public const REVIEW_STATUSES = ['approved', 'rejected'];

    /**
     * Create (or re-approve) one prerequisite edge.
     *
     * Idempotent against the unique keys on both tables: re-drawing a link a curator
     * already drew updates it to approved rather than failing or duplicating.
     *
     * @return array{relation: array, warnings: array<int, string>, created: bool}
     *
     * @throws \InvalidArgumentException on a malformed or mixed-level pair
     */
    public function create(
        int $tenant,
        ?int $actorId,
        string $prerequisiteRef,
        string $dependentRef,
        string $relationType = 'requires',
        ?string $note = null
    ): array {
        [$preType, $preId] = $this->parse($prerequisiteRef);
        [$depType, $depId] = $this->parse($dependentRef);

        if ($prerequisiteRef === $dependentRef) {
            throw new \InvalidArgumentException('A node cannot be its own prerequisite.');
        }

        if (! in_array($relationType, ['requires', 'cross_curricular'], true)) {
            throw new \InvalidArgumentException('relation_type must be requires or cross_curricular.');
        }

        $isConceptPair = $preType === 'concept' && $depType === 'concept';

        if (! $isConceptPair && ! LearningRelation::handles($preType, $depType)) {
            throw new \InvalidArgumentException(
                'Both ends of a relation must be concepts, or neither. Got '.$preType.' and '.$depType.'.'
            );
        }

        $this->assertExists($preType, $preId);
        $this->assertExists($depType, $depId);

        $warnings = $this->wouldCycle($prerequisiteRef, $dependentRef, $tenant)
            ? ['creates_cycle']
            : [];

        if ($isConceptPair) {
            [$row, $created, $previous] = $this->upsertConceptRelation($tenant, $preId, $depId, $relationType);
            $source = 'concept';
        } else {
            [$row, $created, $previous] = $this->upsertLearningRelation($tenant, $preType, $preId, $depType, $depId, $relationType, $note);
            $source = 'learning';
        }

        $this->audit($source, (int) $row->id, $created ? 'created' : 'approved', $previous, 'approved', $tenant, $actorId, $prerequisiteRef, $dependentRef, $relationType, $note);

        return [
            'relation' => $this->present($source, $row, $prerequisiteRef, $dependentRef),
            'warnings' => $warnings,
            'created' => $created,
        ];
    }

    /**
     * Approve or reject an existing edge.
     *
     * Reject is soft on purpose: the row stays with quality_status 'rejected' so the
     * next AI tagging pass can see a person already said no, instead of proposing it
     * again next week. Only a delete removes a row.
     *
     * @return array{relation: array, previous_status: string}
     */
    public function review(int $tenant, ?int $actorId, string $source, int $relationId, string $status, ?string $note = null): array
    {
        if (! in_array($status, self::REVIEW_STATUSES, true)) {
            throw new \InvalidArgumentException('status must be approved or rejected.');
        }

        [$table, $model] = $this->resolveSource($source);

        $row = $this->locate($table, $relationId, $tenant);
        $previous = (string) ($row->quality_status ?: 'draft');

        DB::table($table)->where('id', $relationId)->update([
            'quality_status' => $status,
            // A person's decision replaces the machine's claim to have authored it.
            'tagged_by' => 'human',
            'updated_at' => Carbon::now(),
        ]);

        $row = $this->locate($table, $relationId, $tenant);
        [$preRef, $depRef] = $this->refsFor($source, $row);

        $this->audit($source, $relationId, $status, $previous, $status, $tenant, $actorId, $preRef, $depRef, (string) $row->relation_type, $note);

        return [
            'relation' => $this->present($source, $row, $preRef, $depRef),
            'previous_status' => $previous,
        ];
    }

    /**
     * Remove an edge outright.
     *
     * The audit row is written BEFORE the delete and carries from_ref/to_ref, so the
     * history of a removed edge is still readable once the row is gone.
     */
    public function delete(int $tenant, ?int $actorId, string $source, int $relationId, ?string $note = null): void
    {
        [$table] = $this->resolveSource($source);

        $row = $this->locate($table, $relationId, $tenant);
        [$preRef, $depRef] = $this->refsFor($source, $row);

        $this->audit(
            $source, $relationId, 'deleted', (string) ($row->quality_status ?: 'draft'), null,
            $tenant, $actorId, $preRef, $depRef, (string) $row->relation_type, $note
        );

        DB::table($table)->where('id', $relationId)->delete();
    }

    /**
     * Approve or reject many edges in one action.
     *
     * Not wrapped in a transaction on purpose: a curator selecting eighty suggestions
     * wants the seventy-nine valid ones applied, not all of them rolled back because
     * one had been deleted in another tab. Failures are reported per item.
     *
     * @param  array<int, array{source: string, id: int}>  $items
     * @return array{updated: int, failed: array<int, array{id: int, source: string, error: string}>}
     */
    public function bulkReview(int $tenant, ?int $actorId, array $items, string $status): array
    {
        $updated = 0;
        $failed = [];

        foreach ($items as $item) {
            try {
                $this->review($tenant, $actorId, (string) $item['source'], (int) $item['id'], $status);
                $updated++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'id' => (int) ($item['id'] ?? 0),
                    'source' => (string) ($item['source'] ?? ''),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    // ══════════════════════════════════════════════════════════════════
    // Writes
    // ══════════════════════════════════════════════════════════════════

    /** @return array{0: object, 1: bool, 2: ?string} */
    private function upsertConceptRelation(int $tenant, int $preId, int $depId, string $relationType): array
    {
        // Storage direction: from = dependent, to = prerequisite.
        $match = [
            'from_concept_id' => $depId,
            'to_concept_id' => $preId,
            'relation_type' => $relationType,
            'sub_institute_id' => $tenant,
        ];

        $existing = DB::table('pal_concept_relations')->where($match)->first();
        $now = Carbon::now();

        if ($existing) {
            DB::table('pal_concept_relations')->where('id', $existing->id)->update([
                'quality_status' => 'approved',
                'tagged_by' => 'human',
                'updated_at' => $now,
            ]);

            return [DB::table('pal_concept_relations')->find($existing->id), false, (string) ($existing->quality_status ?: 'draft')];
        }

        $id = DB::table('pal_concept_relations')->insertGetId($match + [
            'scope' => 'tenant',
            // Drawn by a person, so it is confirmed curriculum from the moment it exists.
            'quality_status' => 'approved',
            'tagged_by' => 'human',
            'auto_suggest' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [DB::table('pal_concept_relations')->find($id), true, null];
    }

    /** @return array{0: object, 1: bool, 2: ?string} */
    private function upsertLearningRelation(
        int $tenant, string $preType, int $preId, string $depType, int $depId, string $relationType, ?string $note
    ): array {
        $match = [
            'from_node_type' => $depType,
            'from_node_id' => $depId,
            'to_node_type' => $preType,
            'to_node_id' => $preId,
            'relation_type' => $relationType,
            'sub_institute_id' => $tenant,
        ];

        $existing = DB::table('pal_learning_relations')->where($match)->first();
        $now = Carbon::now();

        if ($existing) {
            DB::table('pal_learning_relations')->where('id', $existing->id)->update([
                'quality_status' => 'approved',
                'tagged_by' => 'human',
                'note' => $note ?: $existing->note,
                'updated_at' => $now,
            ]);

            return [DB::table('pal_learning_relations')->find($existing->id), false, (string) ($existing->quality_status ?: 'draft')];
        }

        $id = DB::table('pal_learning_relations')->insertGetId($match + [
            'scope' => 'tenant',
            'quality_status' => 'approved',
            'tagged_by' => 'human',
            'note' => $note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [DB::table('pal_learning_relations')->find($id), true, null];
    }

    private function audit(
        string $source, int $relationId, string $action, ?string $previous, ?string $new,
        int $tenant, ?int $actorId, string $preRef, string $depRef, ?string $relationType, ?string $note
    ): void {
        DB::table('pal_relation_audit')->insert([
            'relation_source' => $source,
            'relation_id' => $relationId,
            // Stored as rendered, prerequisite first, so the audit reads the same way
            // round as the map the curator was looking at.
            'from_ref' => $preRef,
            'to_ref' => $depRef,
            'relation_type' => $relationType,
            'action' => $action,
            'previous_status' => $previous,
            'new_status' => $new,
            'actor_user_id' => $actorId,
            'sub_institute_id' => $tenant,
            'note' => $note,
            'created_at' => Carbon::now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Checks
    // ══════════════════════════════════════════════════════════════════

    /**
     * Would making $dependent require $prerequisite close a ring?
     *
     * It would if $prerequisite already depends, transitively, on $dependent. Walks
     * the existing `requires` edges backwards from $prerequisite with an explicit
     * visited-set, because the graph it is walking is itself already cyclic.
     *
     * Concept-only: there is no meaningful cross-level ring yet, and walking one
     * would need both tables merged for a check whose answer is advisory anyway.
     */
    private function wouldCycle(string $prerequisiteRef, string $dependentRef, int $tenant): bool
    {
        [$preType, $preId] = $this->parse($prerequisiteRef);
        [$depType, $depId] = $this->parse($dependentRef);

        if ($preType !== 'concept' || $depType !== 'concept') {
            return false;
        }

        $seen = [];
        $queue = [$preId];

        while ($queue !== []) {
            $current = array_pop($queue);

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            if ($current === $depId) {
                return true;
            }

            // What does $current itself require?
            $next = DB::table('pal_concept_relations')
                ->whereIn('sub_institute_id', array_unique([$tenant, 0]))
                ->where('relation_type', 'requires')
                ->where('quality_status', '!=', 'rejected')
                ->where('from_concept_id', $current)
                ->pluck('to_concept_id');

            foreach ($next as $n) {
                if (! isset($seen[(int) $n])) {
                    $queue[] = (int) $n;
                }
            }
        }

        return false;
    }

    /** @return array{0: string, 1: int} */
    private function parse(string $ref): array
    {
        $parts = explode(':', $ref, 2);

        if (count($parts) !== 2 || ! in_array($parts[0], self::TYPES, true) || ! ctype_digit($parts[1])) {
            throw new \InvalidArgumentException(
                'Node reference must look like concept:123 (one of '.implode(', ', self::TYPES).'). Got "'.$ref.'".'
            );
        }

        return [$parts[0], (int) $parts[1]];
    }

    /**
     * Refuse an edge to a node that is not there.
     *
     * Without this a typo creates an edge pointing at nothing, which renders as an
     * arrow into empty space and cannot be selected to delete.
     */
    private function assertExists(string $type, int $id): void
    {
        $table = [
            'concept' => 'lms_concept',
            'topic' => 'topic_master',
            'chapter' => 'chapter_master',
            'unit' => 'lms_units',
        ][$type];

        if (! DB::table($table)->where('id', $id)->exists()) {
            throw new \InvalidArgumentException('No '.$type.' with id '.$id.'.');
        }
    }

    /** @return array{0: string, 1: string} */
    private function resolveSource(string $source): array
    {
        return match ($source) {
            'concept' => ['pal_concept_relations', 'concept'],
            'learning' => ['pal_learning_relations', 'learning'],
            default => throw new \InvalidArgumentException('source must be concept or learning.'),
        };
    }

    /**
     * Fetch a row, refusing to touch another tenant's edge.
     *
     * Shared edges (sub_institute_id 0) are readable by every tenant but must not be
     * editable by one of them, so this deliberately matches the tenant exactly rather
     * than using the IN (tenant, 0) rule the reads use.
     */
    private function locate(string $table, int $id, int $tenant): object
    {
        $row = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenant)->first();

        if (! $row) {
            throw new \RuntimeException('Relation '.$id.' was not found for this institute.');
        }

        return $row;
    }

    /** @return array{0: string, 1: string} prerequisite ref, dependent ref */
    private function refsFor(string $source, object $row): array
    {
        if ($source === 'concept') {
            return ['concept:'.$row->to_concept_id, 'concept:'.$row->from_concept_id];
        }

        return [
            $row->to_node_type.':'.$row->to_node_id,
            $row->from_node_type.':'.$row->from_node_id,
        ];
    }

    /**
     * One edge in the same shape CurriculumGraphBuilder emits, so the client can drop
     * the response straight into its edge list without a second fetch.
     */
    private function present(string $source, object $row, string $preRef, string $depRef): array
    {
        $status = ($row->quality_status ?? 'draft') === 'approved' ? 'approved' : 'draft';

        return [
            'id' => ($source === 'concept' ? 'concept-rel:' : 'learning-rel:').$row->id,
            'relation_id' => (int) $row->id,
            'source_table' => $source,
            'source' => $preRef,
            'target' => $depRef,
            'kind' => $row->relation_type === 'cross_curricular' ? 'cross_curricular' : 'prerequisite',
            'relation_type' => (string) $row->relation_type,
            'status' => $row->quality_status === 'rejected' ? 'rejected' : $status,
            'tagged_by' => (string) ($row->tagged_by ?: 'human'),
            'link_type' => $row->link_type ?? null,
            'confidence' => isset($row->confidence) && $row->confidence !== null ? (float) $row->confidence : null,
            'note' => $row->note ?? null,
        ];
    }
}
