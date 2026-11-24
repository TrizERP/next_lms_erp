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
    public function generate(int $chapterId, array $context, $subInstituteId, string $note = '', array $concepts = [], string $level = 'concepts'): array
    {
        // A lab is mapped to the concepts the teacher chose (default: all of the chapter's); a whole-chapter lab connects them all.
        $context = self::narrow($context, $level === 'chapter' ? [] : $concepts);
        $messages = $this->messages($chapterId, $context, $note, $level);
        $spec = null;
        $validation = ['errors' => ['The AI did not return a lab.'], 'warnings' => [], 'stats' => []];
        $attempts = 0;
        $lastError = null;

        for ($i = 0; $i < 2; $i++) {
            $attempts++;
            try {
                $raw = $this->ask($messages, $subInstituteId, $i === 0);
            } catch (\Throwable $e) {
                Log::warning('Lab spec generation failed', ['error' => $e->getMessage()]);
                $lastError = 'The AI service did not answer. Try again in a minute.';
                break;
            }
            $draft = self::normalise(self::decode((string) $raw), $chapterId, $context, $level);
            if ($draft === null) {
                $cutOff = ! preg_match('/\}\s*(```)?\s*$/', trim((string) $raw));
                $lastError = $cutOff
                    ? 'The AI reply was cut off before the lab was complete. Try again, or choose fewer concepts.'
                    : 'The AI reply was not a usable lab specification.';
                $messages[] = ['role' => 'assistant', 'content' => $cutOff ? mb_substr((string) $raw, 0, 400).' …(cut off)' : (string) $raw];
                $messages[] = ['role' => 'user', 'content' => $cutOff
                    ? 'Your reply was cut off. Return the COMPLETE JSON object again, and keep it compact: a short scenario, at most 10 items, one short sentence for every text field, no line breaks inside text.'
                    : 'That was not valid JSON for the schema. Return ONE complete JSON object only, with no line breaks inside text values.'];
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

    /**
     * Narrows the chapter's concepts to the ones the lab is for (all of them when none are named, or for a whole-chapter lab).
     *
     * @param  array<string,mixed>  $context
     * @param  array<int,string>  $chosen
     * @return array<string,mixed>
     */
    public static function narrow(array $context, array $chosen): array
    {
        if ($chosen === []) {
            return $context;
        }
        $want = array_map('mb_strtolower', $chosen);
        $kept = array_values(array_filter($context['concepts'] ?? [], fn ($c) => in_array(mb_strtolower($c['name']), $want, true)));
        if ($kept !== []) {
            $context['concepts'] = $kept;
        }

        return $context;
    }

    /**
     * One request to the model. A lab is a long, exact JSON document: a thinking model can spend nearly all of its output allowance
     * reasoning and stop mid-document, so reasoning is switched off for this call. If that is refused (a model that cannot run without
     * thinking), the request is made once more as it was.
     */
    private function ask(array $messages, $subInstituteId, bool $first): ?string
    {
        $client = $this->client($subInstituteId);
        $call = fn () => $client->chat($messages, null, 16000, 0.4, true, 170);
        try {
            return \App\Domain\AI\Support\GeminiClient::withThinkingBudget(0, $call);
        } catch (\Throwable $e) {
            if (! $first) {
                throw $e;
            }
            Log::info('Lab spec generation: the model would not answer without thinking; asking as it was.', ['error' => $e->getMessage()]);

            return $call();
        }
    }

    /** @return array<string,mixed>|null */
    public static function decode(string $raw): ?array
    {
        $json = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $data = json_decode($json, true);
        if (! is_array($data)) {
            // A raw line break or tab inside a string is a control character JSON does not allow: make each a space.
            $clean = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/s', fn ($m) => preg_replace('/[\x00-\x1F]+/', ' ', $m[0]), $json);
            $data = json_decode((string) $clean, true);
        }

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
    public static function normalise(?array $d, int $chapterId, array $context, string $level = 'concepts'): ?array
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
        // An item with no settings: a model often leaves "props" out; every later reader expects the list.
        // A drawing the library does not have (a model invents names such as "fan"): the generic drawing, so the lab still opens.
        $d['items'] = array_map(function ($i) {
            if (! is_array($i)) {
                return $i;
            }
            $i += ['props' => []];
            if (! in_array($i['icon'] ?? '', LabSpecValidator::ICONS, true)) {
                $i['icon'] = 'generic';
            }

            return $i;
        }, (array) $d['items']);
        $d['schema'] = 1;
        $d['subject'] = 'Science';
        $d['chapter_ids'] = [$chapterId];
        $d['concepts'] = array_keys($concepts);
        $d['scope_level'] = $level === 'chapter' ? 'chapter' : 'concepts';
        // One key per lab: several labs of a chapter (different concepts, or whole-chapter) must not share one and become versions of each other.
        $d['lab_key'] = substr('c'.$chapterId.'_'.($slug !== '' ? $slug : 'lab'), 0, 26).'_'.substr(md5(($d['title'] ?? '').'|'.implode('|', $d['concepts']).'|'.$d['scope_level']), 0, 5);

        return $d;
    }

    /** @return array<int,array{role:string,content:string}> */
    private function messages(int $chapterId, array $context, string $note, string $level = 'concepts'): array
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
7. "marker" is the answer key, for the teacher and the marker only. "expected" has ONE entry for EVERY setting that appears in a model term (none left out), and EVERY entry has ALL five fields: {"setting": the setting key, "from": an option value, "to": a different option value, "direction": exactly "increase" or "decrease" (what happens to the MEASURED value going from "from" to "to"), "effect": the scientific explanation in a full sentence of 20-400 characters}. The direction must be what your own model really does; it is checked automatically. "concept_focus" says in one or two sentences which ideas of the chapter the lab assesses.
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
            'this_lab_is_for' => $level === 'chapter'
                ? 'the WHOLE chapter: it is offered only once a student has studied every concept, so it must connect several of the concepts rather than test one'
                : 'only the concepts listed above: it is offered to a student who has studied them, so it must not need any other concept of the chapter',
            'teacher_note' => $note !== '' ? mb_substr($note, 0, 600) : null,
            'example_of_the_exact_shape_for_a_different_chapter_do_not_copy_its_content' => json_decode($example, true),
        ];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
    }
}
