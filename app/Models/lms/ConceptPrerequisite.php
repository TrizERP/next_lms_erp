<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One "learn A before B" link between two `lms_concept` rows.
 *
 * Direction: `concept_id` is the LATER concept, `prerequisite_id` the EARLIER one.
 * Read a row as "concept_id requires prerequisite_id".
 *
 * Post-requisites are not stored. They are this same table read from the other end,
 * which is what `scopeUnlockedBy` does.
 */
class ConceptPrerequisite extends Model
{
    protected $table = 'concept_prerequisite';

    /** A hard gate: the learner cannot start without it. */
    public const REQUIRES = 'requires';

    /** Assumed, but a teacher can recover it inside the lesson. */
    public const BUILDS_ON = 'builds_on';

    /** The same idea at an earlier class - the CBSE spiral. */
    public const SPIRAL = 'spiral';

    /** From another subject, almost always Mathematics into Science. */
    public const CROSS_SUBJECT = 'cross_subject';

    /**
     * Every type points one way, so this table can never hold a symmetric pair.
     * Anything added here must also be directed, or the chain walk can loop.
     */
    public const LINK_TYPES = [
        self::REQUIRES,
        self::BUILDS_ON,
        self::SPIRAL,
        self::CROSS_SUBJECT,
    ];

    /**
     * How deep a chain may be walked before we stop.
     *
     * A guard, not a curriculum judgement: classes 6-10 give at most five grade
     * steps, so anything approaching this is a data fault rather than a long chain.
     */
    public const MAX_DEPTH = 24;

    /** More direct prerequisites than this usually means the concept is too coarse. */
    public const MAX_DIRECT_PREREQUISITES = 5;

    protected $fillable = [
        'concept_id', 'prerequisite_id', 'link_type', 'is_gate',
        'concept_grade', 'prerequisite_grade',
        'reason', 'source_ref', 'origin', 'status',
        'sub_institute_id', 'created_by',
    ];

    protected $casts = [
        'is_gate' => 'boolean',
        'concept_grade' => 'integer',
        'prerequisite_grade' => 'integer',
    ];

    // ── reads ───────────────────────────────────────────────────────────────

    /** What this concept needs. One step back. */
    public function scopePrerequisitesOf($query, int $conceptId)
    {
        return $query->where('concept_id', $conceptId);
    }

    /** What this concept unlocks. One step forward - the post-requisite read. */
    public function scopeUnlockedBy($query, int $conceptId)
    {
        return $query->where('prerequisite_id', $conceptId);
    }

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }

    /** Only the links that actually stop a learner. */
    public function scopeGating($query)
    {
        return $query->where('is_gate', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * The whole chain beneath a concept, every step down to a class-6 root.
     *
     * Answers "this student is stuck on X - what are they actually missing?", which
     * is the question the one-step read cannot. MariaDB on this estate is 10.11, so
     * WITH RECURSIVE is available and the walk happens in the database rather than in
     * a PHP loop pulling one level at a time.
     *
     * `depth` is the number of steps from the starting concept, so ordering by it
     * descending puts the deepest missing idea first - which is the one worth
     * remediating, since fixing a shallow gap leaves the learner still blocked.
     *
     * @return array<int, object{prerequisite_id:int, depth:int}>
     */
    public static function chainBelow(int $conceptId, int $subInstituteId = 0): array
    {
        return DB::select(
            'WITH RECURSIVE chain AS (
                SELECT prerequisite_id, concept_id, 1 AS depth
                  FROM concept_prerequisite
                 WHERE concept_id = ? AND sub_institute_id IN (?, 0)
                UNION ALL
                SELECT p.prerequisite_id, p.concept_id, c.depth + 1
                  FROM concept_prerequisite p
                  JOIN chain c ON p.concept_id = c.prerequisite_id
                 WHERE c.depth < ? AND p.sub_institute_id IN (?, 0)
            )
            SELECT prerequisite_id, MAX(depth) AS depth
              FROM chain
             GROUP BY prerequisite_id
             ORDER BY depth DESC',
            [$conceptId, $subInstituteId, self::MAX_DEPTH, $subInstituteId]
        );
    }

    /**
     * Everything a concept unlocks, all the way down the tree.
     *
     * The same query with the two id columns swapped - which is the point of storing
     * one direction. Answers "this student mastered X, what does that open up?".
     *
     * @return array<int, object{concept_id:int, depth:int}>
     */
    public static function chainAbove(int $conceptId, int $subInstituteId = 0): array
    {
        return DB::select(
            'WITH RECURSIVE chain AS (
                SELECT concept_id, prerequisite_id, 1 AS depth
                  FROM concept_prerequisite
                 WHERE prerequisite_id = ? AND sub_institute_id IN (?, 0)
                UNION ALL
                SELECT p.concept_id, p.prerequisite_id, c.depth + 1
                  FROM concept_prerequisite p
                  JOIN chain c ON p.prerequisite_id = c.concept_id
                 WHERE c.depth < ? AND p.sub_institute_id IN (?, 0)
            )
            SELECT concept_id, MIN(depth) AS depth
              FROM chain
             GROUP BY concept_id
             ORDER BY depth ASC',
            [$conceptId, $subInstituteId, self::MAX_DEPTH, $subInstituteId]
        );
    }
}
