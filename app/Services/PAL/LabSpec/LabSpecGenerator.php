<?php

namespace App\Services\PAL\LabSpec;

use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Support\Facades\Log;

/**
 * Drafts a lab specification for a chapter with the AI. Only a teacher or admin can start this (the controller
 * refuses everyone else), and its output is only ever a DRAFT: it is validated here, saved as a draft version, and
 * reaches students only if a teacher or admin approves it.
 *
 * The AI stocks the lab, like a teacher preparing a practical: it chooses the equipment, the settings, how the
 * result responds, and writes the key. It never designs a student's experiment and it never runs at all while a
 * student works; the experiment is the student's, run through the deterministic result model in the spec.
 *
 * If the first draft fails validation the validator's errors are sent back once and a corrected draft is requested.
 * Whatever comes back is saved with its validation report so the teacher can see exactly what is wrong.
 */
class LabSpecGenerator
{
    public function __construct(private ?object $client = null)
    {
    }

    private function client($subInstituteId): object
    {
        return $this->client ?? app(AiModelClientFactory::class)->for('pal_intelligence', $subInstituteId);
    }

    /**
     * @param  array<string,mixed>  $context  LabChapterContext::for()
     * @return array{spec:?array<string,mixed>,validation:array<string,mixed>,attempts:int,error:?string}
     */
    public function generate(int $chapterId, array $context, $subInstituteId, string $note = ''): array
    {
        $messages = $this->messages($chapterId, $context, $note);
        $spec = null;
        $validation = ['errors' => ['The AI did not return a lab.'], 'warnings' => [], 'stats' => []];
        $attempts = 0;
        $lastError = null;

        for ($i = 0; $i < 2; $i++) {
            $attempts++;
            try {
                $raw = $this->client($subInstituteId)->chat($messages, null, 7000, 0.4, true, 120);
            } catch (\Throwable $e) {
                Log::warning('Lab spec generation failed', ['error' => $e->getMessage()]);
                $lastError = 'The AI service did not answer. Try again in a minute.';
                break;
            }
            $draft = self::normalise(self::decode((string) $raw), $chapterId, $context);
            if ($draft === null) {
                $lastError = 'The AI reply was not a usable lab specification.';
                $messages[] = ['role' => 'assistant', 'content' => (string) $raw];
                $messages[] = ['role' => 'user', 'content' => 'That was not valid JSON for the schema. Return ONE complete JSON object only.'];
                continue;
            }
            $spec = $draft;
            $validation = LabSpecValidator::validate($spec);
            $lastError = null;
            if ($validation['errors'] === []) {
                break;
            }
            // One correction round, with the exact errors.
            $messages[] = ['role' => 'assistant', 'content' => (string) $raw];
            $messages[] = ['role' => 'user', 'content' => "The specification failed these checks. Fix every one and return the complete corrected JSON object only:\n- ".implode("\n- ", array_slice($validation['errors'], 0, 20))];
        }

        return ['spec' => $spec, 'validation' => $validation, 'attempts' => $attempts, 'error' => $lastError];
    }

    /** @return array<string,mixed>|null */
    public static function decode(string $raw): ?array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $data = json_decode((string) $json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * What the AI cannot be trusted to set: the schema, subject, chapter and the concept names (which must be the
     * chapter's own), and a key that is unique to this chapter. Everything else is the AI's and is validated.
     *
     * @param  array<string,mixed>|null  $d
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>|null
     */
    public static function normalise(?array $d, int $chapterId, array $context): ?array
    {
        if ($d === null || ! isset($d['items'], $d['model'])) {
            return null;
        }
        $known = [];
        foreach ($context['concepts'] ?? [] as $c) {
            $known[mb_strtolower($c['name'])] = $c['name'];
        }
        $concepts = [];
        foreach ((array) ($d['concepts'] ?? []) as $c) {
            $k = mb_strtolower(trim((string) $c));
            if (isset($known[$k])) {
                $concepts[$known[$k]] = true;
            }
        }
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) ($d['title'] ?? 'lab')), '_'));
        $d['schema'] = 1;
        $d['subject'] = 'Science';
        $d['chapter_ids'] = [$chapterId];
        $d['concepts'] = array_keys($concepts);
        $d['lab_key'] = substr('c'.$chapterId.'_'.($slug !== '' ? $slug : 'lab'), 0, 32);

        return $d;
    }

    /** @return array<int,array{role:string,content:string}> */
    private function messages(int $chapterId, array $context, string $note): array
    {
        $icons = implode(', ', LabSpecValidator::ICONS);
        $groups = implode(', ', LabSpecValidator::GROUPS);
        $units = implode(' ', LabSpecValidator::UNITS);
        $example = json_encode(LabSpecRepository::builtins()['dissolving'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $system = <<<'PROMPT'
You are stocking a virtual science laboratory for a school chapter, the way a teacher prepares a practical. You are NOT designing an experiment for a student and you are NOT writing steps. You produce a LAB SPECIFICATION (one JSON object): the equipment on the shelf, the settings each piece has, what can be measured, and a deterministic model of how the measured result responds to the settings. Students will then design their own investigation in it. A teacher will review and approve what you write; it is never shown to students otherwise.

Hard rules
1. Science only, and only what the chapter's concepts support. Use the chapter's own concept names, exactly as given, in "concepts" (choose the ones this lab assesses).
2. "scenario" is a real-life PROBLEM that needs investigating (60-900 characters). It never states a method, a set-up, steps, apparatus to use or the answer. "outcomes" are three to six things a good student answer must DO (not steps).
3. The shelf ("items") has 8-16 pieces: everything an investigation of this problem could sensibly use (materials, containers, conditions, tools, instruments), plus at least two pieces that have NOTHING to do with the problem ("irrelevant": ids; they must have no settings and must not be in an essential group), so choosing equipment is a real decision.
4. Each setting (a "prop" of an item) has 2-4 options, a default, and (when the piece may be absent from the bench) an "absent" value that means "not used". Give numeric options a "num" (the number used in formulas) and, where it helps the picture, "vis" hints: scale (0.2-3), tone (clear, cream, grey, brown, green, blue, red), heat (degrees), grains (fine, coarse, lump), spin (0-1), cover (true).
5. Exactly one measure (what the experiment measures, with its unit and the instrument item that measures it). An instrument can have "reads": {"kind":"clock"} or {"kind":"setting","setting":"<a setting key>","unit":"..."}.
6. "model" is deterministic: result = base x product of terms, clamped to min and max. Term types: lookup {setting, table:{option:multiplier}, default} (the table MUST cover every option); power {setting, [per], ref, exp} = ((x [/ y]) / ref)^exp; exp {setting, ref, k} = e^(k (x - ref)); linear {setting, ref, slope, floor}; interp {setting, points:[[x,multiplier],...]} (piecewise linear, held flat beyond the ends); rule {when:[{setting, eq|in}], multiply}. Every multiplier is positive. Use real scientific relationships with realistic magnitudes (times in seconds that a student can watch in a few minutes, 10 s to 600 s). Every setting that matters must appear in a term. The settings together must change the result by at least a factor of 2.
7. "marker" is the answer key, for the teacher and the marker only: "expected" lists, for each setting that matters, {setting, from (an option), to (a different option), direction ("increase" or "decrease": what happens to the MEASURED value going from "from" to "to"), effect (the scientific explanation, 20-400 characters)}. The direction must be what your own model really does; it is checked automatically. "concept_focus" says in one or two sentences which ideas of the chapter the lab assesses.
8. "visual": {"kind": one of shrink (a solid disappears), dissolve (grains disappear), grow (something gets bigger), dial (a gauge fills), "emoji": one emoji for the material, "exponent": how the picture progresses, 1-3}.
9. Allowed values only. Icons (use the drawing that matches the item): ICONS. Groups: GROUPS. Units: UNITS. Setting keys and ids are lowercase letters, digits, underscores.
10. Return ONE JSON object in exactly the shape of the example, and nothing else.
PROMPT;
        $system = str_replace(['ICONS', 'GROUPS', 'UNITS'], [$icons, $groups, $units], $system);

        $user = [
            'chapter' => $context['chapter'], 'subject' => $context['subject'], 'standard' => $context['standard'],
            'chapter_summary' => $context['summary'], 'key_concepts' => $context['key_concepts'],
            'concepts_you_may_use_exactly_as_named' => $context['concepts'],
            'chapter_id' => $chapterId,
            'teacher_note' => $note !== '' ? mb_substr($note, 0, 600) : null,
            'example_of_the_exact_shape_for_a_different_chapter_do_not_copy_its_content' => json_decode($example, true),
        ];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
    }
}
