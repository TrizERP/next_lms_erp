<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\CurriculumVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creating and superseding curriculum versions.
 *
 * The one behaviour worth stating plainly, because it is the entire point of
 * the feature: SUPERSEDING NEVER TOUCHES TAGGED CONTENT. When a board revises
 * its syllabus, the old version is marked superseded and a new one is created;
 * every content and question row that pointed at the old version still points
 * at it afterwards. That is what keeps a past cohort's evidence attached to the
 * material they were actually taught from.
 *
 * The alternative — repointing rows at the new version — would silently rewrite
 * history, and is the destructive migration this item exists to avoid.
 */
class CurriculumVersionService
{
    /** 'YYYY-YY', the Indian academic-year convention. */
    private const YEAR_PATTERN = '/^\d{4}-\d{2}$/';

    /**
     * Find the version for a scope and year, or create it as a draft.
     *
     * Idempotent: the unique key means two callers racing to open the same
     * year converge on one row rather than creating a duplicate.
     */
    public function resolve(
        int $subInstituteId,
        string $board,
        int $standardId,
        ?int $subjectId,
        string $academicYear,
        ?string $label = null
    ): CurriculumVersion {
        $this->assertYear($academicYear);

        $existing = CurriculumVersion::query()
            ->forTenant($subInstituteId)
            ->forScope($board, $standardId, $subjectId)
            ->where('academic_year', $academicYear)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return CurriculumVersion::create([
            'sub_institute_id' => $subInstituteId,
            'board' => $board,
            'standard_id' => $standardId,
            // Normalised through the model so a standard-wide version stores 0
            // rather than NULL — see CurriculumVersion::subjectKey().
            'subject_id' => CurriculumVersion::subjectKey($subjectId),
            'academic_year' => $academicYear,
            'label' => $label ?? $this->defaultLabel($board, $standardId, $subjectId, $academicYear),
            // Created as a draft, never active: a new syllabus year is not
            // servable the moment someone names it, and activating it is a
            // deliberate act (see activate()).
            'status' => CurriculumVersion::STATUS_DRAFT,
        ]);
    }

    /** The version learners are currently taught from for a scope, if any. */
    public function activeFor(
        int $subInstituteId,
        string $board,
        int $standardId,
        ?int $subjectId
    ): ?CurriculumVersion {
        return CurriculumVersion::query()
            ->forTenant($subInstituteId)
            ->forScope($board, $standardId, $subjectId)
            ->active()
            ->first();
    }

    /**
     * Make a version the one in force for its scope.
     *
     * Any version already active for the same scope is superseded by this one,
     * because "which syllabus is in force" has exactly one answer at a time.
     * Done in a transaction so there is never a moment with two active
     * versions, or none.
     */
    public function activate(CurriculumVersion $version): CurriculumVersion
    {
        if ($version->isSuperseded()) {
            throw new InvalidArgumentException(
                'A superseded curriculum version cannot be re-activated. Create a new version for the current year instead — reviving an old one would make the cohorts taught under it indistinguishable from the current one.'
            );
        }

        return DB::transaction(function () use ($version) {
            $current = $this->activeFor(
                (int) $version->sub_institute_id,
                (string) $version->board,
                (int) $version->standard_id,
                (int) $version->subject_id
            );

            if ($current !== null && (int) $current->id !== (int) $version->id) {
                $current->status = CurriculumVersion::STATUS_SUPERSEDED;
                $current->superseded_by_id = $version->id;
                $current->save();
            }

            $version->status = CurriculumVersion::STATUS_ACTIVE;
            $version->effective_from ??= now()->toDateString();
            $version->save();

            return $version->refresh();
        });
    }

    /**
     * The successor for a new academic year: create it, point the old version
     * at it, and make it the one in force.
     *
     * Content is deliberately NOT carried across. Copying every row into the
     * new year would assert that the board changed nothing, which is precisely
     * what a revision contradicts — and it would double the estate on every
     * rollover. Authoring decides what the new year reuses; this only opens it.
     */
    public function supersede(CurriculumVersion $version, string $newAcademicYear, ?string $label = null): CurriculumVersion
    {
        $this->assertYear($newAcademicYear);

        if ($newAcademicYear === $version->academic_year) {
            throw new InvalidArgumentException(
                'A version cannot supersede itself. A revision within the same academic year is an edit to that version, not a new one.'
            );
        }

        $successor = $this->resolve(
            (int) $version->sub_institute_id,
            (string) $version->board,
            (int) $version->standard_id,
            (int) $version->subject_id,
            $newAcademicYear,
            $label
        );

        $this->activate($successor);

        // activate() supersedes whichever version was ACTIVE, which is not
        // necessarily the one being superseded here — a draft, or an already
        // superseded year, would otherwise be left with a null
        // `superseded_by_id` and the chain would break at exactly the point
        // someone is trying to trace. Stated explicitly so
        // successorChain() can walk forward from any version, which is the
        // property the whole design rests on.
        $version->refresh();

        if ((int) $version->id !== (int) $successor->id && $version->superseded_by_id === null) {
            $version->status = CurriculumVersion::STATUS_SUPERSEDED;
            $version->superseded_by_id = $successor->id;
            $version->save();
        }

        return $successor->refresh();
    }

    /**
     * Walk the chain forward from any version to the one currently in force.
     *
     * Useful for "what replaced the syllabus this evidence was recorded
     * against?" — answerable without mutating the historical row.
     *
     * @return array<int, CurriculumVersion>
     */
    public function successorChain(CurriculumVersion $version): array
    {
        $chain = [];
        $seen = [];
        $current = $version;

        while ($current->superseded_by_id !== null) {
            // Guard rather than trust: a cycle here would hang a request, and
            // the pointer is only forward-only by convention.
            if (isset($seen[$current->id])) {
                break;
            }
            $seen[$current->id] = true;

            $next = CurriculumVersion::find($current->superseded_by_id);
            if ($next === null) {
                break;
            }

            $chain[] = $next;
            $current = $next;
        }

        return $chain;
    }

    private function assertYear(string $academicYear): void
    {
        if (! preg_match(self::YEAR_PATTERN, $academicYear)) {
            throw new InvalidArgumentException(
                "Academic year must be in 'YYYY-YY' form, e.g. '2026-27'. Got '{$academicYear}'."
            );
        }
    }

    private function defaultLabel(string $board, int $standardId, ?int $subjectId, string $academicYear): string
    {
        $standardName = DB::table('standard')->where('id', $standardId)->value('name');
        $subjectName = $subjectId !== null
            ? DB::table('subject')->where('id', $subjectId)->value('subject_name')
            : null;

        return trim(sprintf(
            '%s %s%s, %s',
            $board,
            $subjectName ? $subjectName . ' ' : '',
            $standardName ? 'Grade ' . $standardName : '',
            $academicYear
        ));
    }
}
