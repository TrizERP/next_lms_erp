<?php

namespace App\Services\lms\Simulation;

use Illuminate\Support\Facades\DB;

/**
 * The link between a simulation and the curriculum concepts it helps teach (`lms_simulation_concept`).
 *
 * ANCHORED ON THE CONCEPT. The client names a concept id and nothing else about where it sits: `standard_id`, `subject_id`, `chapter_id` and
 * `topic_id` are COPIED from `lms_concept` by this class on every write, so a link can never claim a different chapter than its concept's. A concept
 * must be one of the platform tenant's (config lms_content.platform_sub_institute_ids): an unknown id and another tenant's id are the same 422,
 * naming the id.
 *
 * REVIEW. A link starts in `review`; a learner sees nothing through a link until it is `published`. Saving the set again keeps the review state of
 * a link that did not change (same role, citation and reason) and sends a changed one back to `review`, because a teacher approved the old text.
 */
final class SimulationLinks
{
    public const ROLES = ['primary', 'supporting'];
    public const STATUSES = ['review', 'published'];
    public const MAX_LINKS = 200;

    /** @return list<int> the tenants that own the shared curriculum (and so the concepts a link may point at). */
    public function platformTenants(): array
    {
        return array_values(array_unique(array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]))));
    }

    /**
     * The links of the given simulations, with the names a card shows, keyed by simulation id.
     *
     * @param  list<int>  $simulationIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forSimulations(array $simulationIds, bool $publishedOnly): array
    {
        if ($simulationIds === []) {
            return [];
        }
        $q = DB::table('lms_simulation_concept as l')
            ->leftJoin('lms_concept as c', 'c.id', '=', 'l.concept_id')
            ->leftJoin('chapter_master as ch', 'ch.id', '=', 'l.chapter_id')
            ->leftJoin('topic_master as t', 't.id', '=', 'l.topic_id')
            ->whereIn('l.simulation_id', $simulationIds)
            ->orderBy('l.sort_order')->orderBy('l.id')
            ->select('l.*', 'c.name as concept_name', 'ch.chapter_name as chapter_name', 't.name as topic_name');
        if ($publishedOnly) {
            $q->where('l.review_status', 'published');
        }

        $out = [];
        foreach ($q->get() as $row) {
            $out[(int) $row->simulation_id][] = $this->present($row);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function forSimulation(int $simulationId, bool $publishedOnly): array
    {
        return $this->forSimulations([$simulationId], $publishedOnly)[$simulationId] ?? [];
    }

    /** @return array<string, mixed> */
    private function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'concept_id' => (int) $row->concept_id,
            'concept_name' => $row->concept_name,
            'chapter_id' => (int) $row->chapter_id,
            'chapter_name' => $row->chapter_name,
            'topic_id' => $row->topic_id === null ? null : (int) $row->topic_id,
            'topic_name' => $row->topic_name,
            'standard_id' => (int) $row->standard_id,
            'subject_id' => (int) $row->subject_id,
            'role' => $row->role,
            'citation' => $row->citation,
            'reason' => $row->reason,
            'review_status' => $row->review_status,
            'sort_order' => (int) $row->sort_order,
            'reviewed_by' => $row->reviewed_by === null ? null : (int) $row->reviewed_by,
            'reviewed_at' => $row->reviewed_at,
        ];
    }

    /**
     * Replaces a simulation's link set with `$wanted`. Everything is validated first; nothing is written unless every entry is good.
     *
     * @param  list<mixed>  $wanted  entries of { concept_id, role?, citation?, reason?, sort_order? }
     * @return list<array<string, mixed>>  the links as saved
     *
     * @throws SimulationException  422 naming the first problems
     */
    public function replace(object $sim, array $wanted, ?int $userId): array
    {
        if (count($wanted) > self::MAX_LINKS) {
            throw new SimulationException(['A simulation may have at most ' . self::MAX_LINKS . ' links.']);
        }

        $problems = [];
        $clean = [];
        foreach ($wanted as $i => $entry) {
            $where = "links.{$i}";
            if (! is_array($entry)) {
                $problems[] = "\"{$where}\" must be an object.";

                continue;
            }
            $conceptId = $entry['concept_id'] ?? null;
            if (! is_int($conceptId) && ! (is_string($conceptId) && ctype_digit($conceptId))) {
                $problems[] = "\"{$where}.concept_id\" must be a concept id (a whole number).";

                continue;
            }
            $conceptId = (int) $conceptId;
            if ($conceptId < 1) {
                $problems[] = "\"{$where}.concept_id\" must be a concept id (a whole number).";

                continue;
            }
            if (isset($clean[$conceptId])) {
                $problems[] = "Concept {$conceptId} is listed twice (\"{$where}\"). A simulation links to a concept once.";

                continue;
            }
            $role = $entry['role'] ?? 'primary';
            if (! is_string($role) || ! in_array($role, self::ROLES, true)) {
                $problems[] = "\"{$where}.role\" must be one of: " . implode(', ', self::ROLES) . '.';
            }
            $citation = $this->text($entry, 'citation', 255, $where, $problems);
            $reason = $this->text($entry, 'reason', 500, $where, $problems);
            $order = $entry['sort_order'] ?? 0;
            if (! is_numeric($order) || (string) (int) $order !== (string) (0 + $order)) {
                $problems[] = "\"{$where}.sort_order\" must be a whole number.";
                $order = 0;
            }
            $clean[$conceptId] = ['role' => is_string($role) ? $role : 'primary', 'citation' => $citation, 'reason' => $reason, 'sort_order' => max(-32000, min(32000, (int) $order))];
        }
        if ($problems !== []) {
            throw new SimulationException($problems);
        }

        // the concepts themselves: the platform tenant's, and nobody else's
        $concepts = $clean === [] ? collect() : DB::table('lms_concept')
            ->whereIn('id', array_keys($clean))->whereIn('sub_institute_id', $this->platformTenants())
            ->select('id', 'standard_id', 'subject_id', 'chapter_id', 'topic_id')->get()->keyBy('id');
        foreach (array_keys($clean) as $conceptId) {
            if (! $concepts->has($conceptId)) {
                $problems[] = "Concept {$conceptId} does not exist, or does not belong to the shared curriculum, so it cannot be linked.";
            }
        }
        if ($problems !== []) {
            throw new SimulationException($problems);
        }

        $now = now();
        DB::transaction(function () use ($sim, $clean, $concepts, $userId, $now) {
            $existing = DB::table('lms_simulation_concept')->where('simulation_id', $sim->id)->get()->keyBy('concept_id');
            $gone = $existing->keys()->diff(array_keys($clean))->all();
            if ($gone !== []) {
                DB::table('lms_simulation_concept')->where('simulation_id', $sim->id)->whereIn('concept_id', $gone)->delete();
            }
            foreach ($clean as $conceptId => $link) {
                $concept = $concepts->get($conceptId);
                $copies = [
                    'standard_id' => (int) $concept->standard_id,
                    'subject_id' => (int) $concept->subject_id,
                    'chapter_id' => (int) $concept->chapter_id,
                    'topic_id' => $concept->topic_id === null ? null : (int) $concept->topic_id,
                ];
                $row = $existing->get($conceptId);
                if ($row === null) {
                    DB::table('lms_simulation_concept')->insert($copies + $link + [
                        'simulation_id' => $sim->id,
                        'concept_id' => $conceptId,
                        'review_status' => 'review',
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }
                $same = $row->role === $link['role'] && $row->citation === $link['citation'] && $row->reason === $link['reason'];
                $update = $copies + $link + ['updated_at' => $now];
                if (! $same) {
                    $update += ['review_status' => 'review', 'reviewed_by' => null, 'reviewed_at' => null];
                }
                DB::table('lms_simulation_concept')->where('id', $row->id)->update($update);
            }
        });

        return $this->forSimulation((int) $sim->id, false);
    }

    /**
     * Adds one link in `review`, with the hierarchy copied from the concept; a link that is already there is left exactly as it is.
     *
     * @param  object  $concept  a lms_concept row with standard_id, subject_id, chapter_id and topic_id
     * @param  array{role: string, citation: ?string, reason: ?string, sort_order: int}  $attrs
     * @return bool  whether it was created
     */
    public function addIfAbsent(object $sim, object $concept, array $attrs, ?int $userId): bool
    {
        if (DB::table('lms_simulation_concept')->where('simulation_id', $sim->id)->where('concept_id', $concept->id)->exists()) {
            return false;
        }
        $now = now();
        DB::table('lms_simulation_concept')->insert($attrs + [
            'simulation_id' => $sim->id,
            'concept_id' => $concept->id,
            'standard_id' => (int) $concept->standard_id,
            'subject_id' => (int) $concept->subject_id,
            'chapter_id' => (int) $concept->chapter_id,
            'topic_id' => $concept->topic_id === null ? null : (int) $concept->topic_id,
            'review_status' => 'review',
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    /**
     * Sets one link to `review` or `published` and stamps who did it and when. The link must be one of THIS simulation's.
     *
     * @return array<string, mixed>|null  the link as saved, or null when this simulation has no such link
     *
     * @throws SimulationException  422 for a status that is not known
     */
    public function review(object $sim, int $linkId, mixed $status, int $userId): ?array
    {
        if (! is_string($status) || ! in_array($status, self::STATUSES, true)) {
            throw new SimulationException(['"status" must be one of: ' . implode(', ', self::STATUSES) . '.']);
        }
        $row = DB::table('lms_simulation_concept')->where('id', $linkId)->where('simulation_id', $sim->id)->first();
        if ($row === null) {
            return null;
        }
        $now = now();
        DB::table('lms_simulation_concept')->where('id', $row->id)->update([
            'review_status' => $status,
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($this->forSimulation((int) $sim->id, false) as $link) {
            if ($link['id'] === (int) $row->id) {
                return $link;
            }
        }

        return null;
    }

    /**
     * The concept picker: platform-tenant concepts, optionally of one chapter and matching a name, with their topic and rating.
     *
     * @return list<array<string, mixed>>
     */
    public function pickConcepts(?int $chapterId, ?string $search, int $limit = 200): array
    {
        $q = DB::table('lms_concept as c')
            ->leftJoin('topic_master as t', 't.id', '=', 'c.topic_id')
            ->whereIn('c.sub_institute_id', $this->platformTenants())
            ->select('c.id', 'c.name', 'c.topic_id', 't.name as topic_name', 'c.chapter_id', 'c.review_status')
            ->orderBy('c.chapter_id')->orderBy('t.topic_sort_order')->orderBy('c.topic_id')->orderBy('c.id')
            ->limit(max(1, min(500, $limit)));
        if ($chapterId !== null) {
            $q->where('c.chapter_id', $chapterId);
        }
        if ($search !== null && trim($search) !== '') {
            $q->where('c.name', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($search)) . '%');
        }

        return $q->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'topic_id' => $r->topic_id === null ? null : (int) $r->topic_id,
            'topic_name' => $r->topic_name,
            'chapter_id' => (int) $r->chapter_id,
            'review_status' => $r->review_status,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $problems
     */
    private function text(array $entry, string $key, int $max, string $where, array &$problems): ?string
    {
        $v = $entry[$key] ?? null;
        if ($v === null) {
            return null;
        }
        if (! is_string($v) || mb_strlen(trim($v)) > $max || ! mb_check_encoding($v, 'UTF-8')) {
            $problems[] = "\"{$where}.{$key}\" must be text of {$max} characters at most.";

            return null;
        }
        $v = trim($v);

        return $v === '' ? null : $v;
    }
}
