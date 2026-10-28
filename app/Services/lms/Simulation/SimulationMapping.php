<?php

namespace App\Services\lms\Simulation;

use Illuminate\Support\Facades\DB;

/**
 * The reviewed mapping of simulations to curriculum concepts (`database/simulation-packages/mapping.json`), which is keyed by NAMES because concept
 * ids differ between K12 environments. This resolves the names to ids inside the platform tenant the way PrayogshalaStandard9ScienceSeeder does,
 * and REFUSES instead of guessing:
 *
 *   - the tuple (standard name, subject name, chapter name, topic name or null, concept name) must match EXACTLY ONE concept of the platform
 *     tenant. Zero is a refusal; more than one is a refusal too (the first is never picked). Names are compared as the database collation
 *     compares them (SQL `=`), so a curly apostrophe or a bracket in a name is matched exactly as stored.
 *   - `topic` may be JSON null: it then matches a concept whose topic_id IS NULL, and the link gets topic_id NULL.
 *   - the simulation named by `slug` must already be imported.
 *
 * The whole file is checked before anything is written: one refusal and nothing is created. Links are created in `review`; a link that already
 * exists is left exactly as it is (its review state included), so running the import again changes nothing.
 */
final class SimulationMapping
{
    public function __construct(private readonly SimulationLinks $links)
    {
    }

    /**
     * Reads the mapping file.
     *
     * @return list<array<string, mixed>>
     *
     * @throws SimulationException
     */
    public function load(string $file): array
    {
        if (! is_file($file)) {
            throw new SimulationException(["No such mapping file: {$file}"]);
        }
        try {
            $mapping = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SimulationException([basename($file) . ' is not valid JSON: ' . $e->getMessage()]);
        }
        if (! is_array($mapping) || ! array_is_list($mapping)) {
            throw new SimulationException([basename($file) . ' must be a JSON list of { "slug": ..., "links": [...] }.']);
        }

        return $mapping;
    }

    /**
     * Resolves every link of the mapping against the database. Writes nothing.
     *
     * @param  list<array<string, mixed>>  $mapping
     * @return array{resolved: list<array{simulation: object, concept: object, link: array<string, mixed>, exists: bool}>, refusals: list<string>}
     */
    public function plan(array $mapping, int $tenant): array
    {
        $resolved = [];
        $refusals = [];
        foreach ($mapping as $i => $entry) {
            $slug = is_array($entry) ? ($entry['slug'] ?? null) : null;
            if (! is_string($slug) || $slug === '' || ! is_array($entry['links'] ?? null)) {
                $refusals[] = "Entry {$i}: needs a \"slug\" and a list of \"links\".";

                continue;
            }
            $sim = DB::table('lms_simulation')->where('sub_institute_id', $tenant)->where('slug', $slug)->whereNull('deleted_at')->first();
            if ($sim === null) {
                $refusals[] = "{$slug}: the simulation is not imported for tenant {$tenant} (run lms:simulations-import first).";

                continue;
            }
            $seen = [];
            foreach ($entry['links'] as $j => $link) {
                $where = "{$slug} link {$j}";
                if (! is_array($link)) {
                    $refusals[] = "{$where}: must be an object.";

                    continue;
                }
                $tuple = $this->describe($link);
                $problem = $this->shapeProblem($link);
                if ($problem !== null) {
                    $refusals[] = "{$where} {$tuple}: {$problem}";

                    continue;
                }
                $matches = $this->match($link, $tenant);
                if (count($matches) !== 1) {
                    $refusals[] = "{$where} {$tuple}: " . (count($matches) === 0
                        ? 'no concept of the platform tenant has these names.'
                        : 'more than one concept (' . implode(', ', array_map(fn ($c) => '#' . $c->id, $matches)) . ') has these names, so none was picked. Make the names unambiguous in the mapping.');

                    continue;
                }
                $concept = $matches[0];
                if (isset($seen[$concept->id])) {
                    $refusals[] = "{$where} {$tuple}: the same concept (#{$concept->id}) is listed twice for this simulation.";

                    continue;
                }
                $seen[$concept->id] = true;
                $resolved[] = [
                    'simulation' => $sim,
                    'concept' => $concept,
                    'link' => $link,
                    'exists' => DB::table('lms_simulation_concept')->where('simulation_id', $sim->id)->where('concept_id', $concept->id)->exists(),
                ];
            }
        }

        return ['resolved' => $resolved, 'refusals' => $refusals];
    }

    /**
     * Creates the links a plan() with no refusals resolved, in `review`. A link that exists is left untouched.
     *
     * @param  list<array{simulation: object, concept: object, link: array<string, mixed>, exists: bool}>  $resolved
     * @return array{created: int, existing: int}
     */
    public function apply(array $resolved, ?int $userId): array
    {
        $created = 0;
        $existing = 0;
        DB::transaction(function () use ($resolved, $userId, &$created, &$existing) {
            foreach ($resolved as $item) {
                $made = $this->links->addIfAbsent($item['simulation'], $item['concept'], [
                    'role' => $item['link']['role'] ?? 'primary',
                    'citation' => $this->clean($item['link']['citation'] ?? null),
                    'reason' => $this->clean($item['link']['reason'] ?? null),
                    'sort_order' => 0,
                ], $userId);
                $made ? $created++ : $existing++;
            }
        });

        return ['created' => $created, 'existing' => $existing];
    }

    /** @param  array<string, mixed>  $link */
    private function shapeProblem(array $link): ?string
    {
        foreach (['standard', 'subject', 'chapter', 'concept'] as $key) {
            if (! is_string($link[$key] ?? null) || $link[$key] === '') {
                return "\"{$key}\" must be a name (text).";
            }
        }
        if (! array_key_exists('topic', $link) || ($link['topic'] !== null && (! is_string($link['topic']) || $link['topic'] === ''))) {
            return '"topic" must be a name, or null for a concept that has no topic.';
        }
        $role = $link['role'] ?? 'primary';
        if (! is_string($role) || ! in_array($role, SimulationLinks::ROLES, true)) {
            return '"role" must be one of: ' . implode(', ', SimulationLinks::ROLES) . '.';
        }
        foreach (['citation' => 255, 'reason' => 500] as $key => $max) {
            $v = $link[$key] ?? null;
            if ($v !== null && (! is_string($v) || mb_strlen(trim($v)) > $max)) {
                return "\"{$key}\" must be text of {$max} characters at most.";
            }
        }

        return null;
    }

    /**
     * The concepts of the platform tenant that carry exactly these names (at most 3 are fetched: all that is needed to tell one from many).
     *
     * @param  array<string, mixed>  $link
     * @return list<object>
     */
    private function match(array $link, int $tenant): array
    {
        $q = DB::table('lms_concept as c')
            ->join('chapter_master as ch', 'ch.id', '=', 'c.chapter_id')
            ->join('standard as st', 'st.id', '=', 'c.standard_id')
            ->join('subject as su', 'su.id', '=', 'c.subject_id')
            ->leftJoin('topic_master as t', 't.id', '=', 'c.topic_id')
            ->where('c.sub_institute_id', $tenant)
            ->where('ch.sub_institute_id', $tenant)
            ->where('st.name', $link['standard'])
            ->where('su.subject_name', $link['subject'])
            ->where('ch.chapter_name', $link['chapter'])
            ->where('c.name', $link['concept']);
        $link['topic'] === null ? $q->whereNull('c.topic_id') : $q->where('t.name', $link['topic']);

        return $q->orderBy('c.id')->limit(3)->select('c.id', 'c.standard_id', 'c.subject_id', 'c.chapter_id', 'c.topic_id', 'c.name')->get()->all();
    }

    /** @param  array<string, mixed>  $link */
    private function describe(array $link): string
    {
        $part = fn ($v) => is_string($v) ? $v : ($v === null ? '(no topic)' : '?');

        return '[' . implode(' > ', [$part($link['standard'] ?? '?'), $part($link['subject'] ?? '?'), $part($link['chapter'] ?? '?'), $part(array_key_exists('topic', $link) ? $link['topic'] : '?'), $part($link['concept'] ?? '?')]) . ']';
    }

    private function clean(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;

        return $v === '' ? null : $v;
    }
}
