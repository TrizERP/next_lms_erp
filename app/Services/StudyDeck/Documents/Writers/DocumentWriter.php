<?php

namespace App\Services\StudyDeck\Documents\Writers;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\ExtractsJson;

/**
 * The part every study-document writer shares: asking the model for structured JSON in chunks, tolerating a reply
 * that is not JSON, asking again for what is missing, and asking once more for what breaks the rules.
 *
 * It is the same discipline as the lesson deck's SlideContentGenerator (the model writes STRUCTURED fields and code
 * owns the markup; a chunk costs a few items, never the document; one retry, one repair round) so a document is no
 * less reliable than a deck. A subclass supplies what is specific to its kind: the prompt, how a raw reply is
 * cleaned, and what counts as a problem. Nothing here knows a subject, a chapter or a concept name.
 */
abstract class DocumentWriter
{
    use ExtractsJson;

    public const BLOOM = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];

    /** @var array<int,string> every raw model reply, kept so a rejected document can be inspected */
    public array $replies = [];

    public function __construct(protected readonly Completer $completer, protected readonly int $chunkSize = 6)
    {
    }

    abstract public function kind(): DocumentKind;

    /**
     * The shared ground rules, repeated in every prompt because every call is a separate conversation.
     */
    protected const GROUND_RULES = <<<'RULES'
- Every fact, definition, number, name, formula and example comes from the CHAPTER TEXT at the end. If the concept data and the chapter text disagree, the chapter text wins. You add nothing from outside it.
- Use the chapter text's own wording for definitions and for anything a learner must reproduce exactly.
- Plain sentence-case language. Address the learner as "you". No emoji, no exclamation marks, no Markdown, no HTML.
RULES;

    protected function ask(string $system, string $prompt, int $maxTokens): string
    {
        return $this->replies[] = $this->completer->complete($system, $prompt, $maxTokens);
    }

    /** The reply as an array, or null when it is not JSON. A reply that is not JSON is a reason to ask again, not a crash. */
    protected function readJson(string $reply): ?array
    {
        try {
            return $this->extractJson($reply);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Ask for one item per work item, in chunks.
     *
     * For each chunk: ask; ask again once for any item the reply left out; then check what came back against the
     * rules and, if anything breaks them, ask ONCE MORE for just those items, showing the model its own text and
     * what is wrong. An item that is still missing after the retry stops the run (a document with a hole in it is
     * worse than none); an item that still breaks a rule is kept and left for the validator to report.
     *
     * @param array<int|string,array<string,mixed>> $work work items by key (a concept id, an activity id)
     * @param callable(array<int|string,array<string,mixed>>):string $prompt the prompt for a set of work items
     * @param callable(array<string,mixed>,array<int|string,array<string,mixed>>):array<int|string,array<string,mixed>> $parse a decoded reply -> cleaned items by key (only keys that were asked for)
     * @param callable(array<string,mixed>,array<string,mixed>):array<int,string> $problems cleaned item, its work item -> what is wrong with it
     * @param callable(int,int):void|null $progress
     * @return array<int|string,array<string,mixed>> cleaned items by key
     */
    protected function inChunks(array $work, string $system, int $maxTokens, string $what, callable $prompt, callable $parse, callable $problems, ?callable $progress = null): array
    {
        $done = [];

        foreach (array_chunk($work, $this->chunkSize, true) as $chunk) {
            $got = $this->take($this->ask($system, $prompt($chunk), $maxTokens), $parse, $chunk);

            $missing = array_diff_key($chunk, $got);
            if ($missing) {
                $retry = $prompt($chunk) . "\n\nYour reply was missing " . $what . ' for: ' . implode(', ', array_map(fn ($k) => $this->label($chunk[$k], (string) $k), array_keys($missing)))
                    . ' (or had them malformed). Return ALL of the items above again, complete, as strictly valid JSON.';
                $got = $this->take($this->ask($system, $retry, $maxTokens), $parse, $chunk) + $got;
            }

            $still = array_diff_key($chunk, $got);
            if ($still) {
                throw new \RuntimeException('No ' . $what . ' was written for ' . implode('; ', array_map(fn ($k) => $this->label($chunk[$k], (string) $k), array_keys($still))) . '.');
            }

            $issues = [];
            foreach ($got as $key => $item) {
                if ($found = $problems($item, $chunk[$key])) {
                    $issues[$key] = $found;
                }
            }
            if ($issues) {
                $notes = [];
                foreach ($issues as $key => $found) {
                    $notes[] = $this->label($chunk[$key], (string) $key) . ': ' . implode(' ', $found) . ' Your current text: ' . json_encode($got[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $fix = $prompt(array_intersect_key($chunk, $issues))
                    . "\n\nThese need fixing. Rewrite each one IN FULL, fixing what is wrong and keeping what was good:\n" . implode("\n", $notes);
                $fixed = $this->take($this->ask($system, $fix, $maxTokens), $parse, array_intersect_key($chunk, $issues));
                foreach ($fixed as $key => $item) {
                    // A rewrite replaces the first attempt unless it is worse.
                    if (count($problems($item, $chunk[$key])) <= count($issues[$key])) {
                        $got[$key] = $item;
                    }
                }
            }

            $done += $got;
            if ($progress) {
                $progress(count($done), count($work));
            }
        }

        return $done;
    }

    /**
     * @param callable(array<string,mixed>,array<int|string,array<string,mixed>>):array<int|string,array<string,mixed>> $parse
     * @param array<int|string,array<string,mixed>> $chunk
     * @return array<int|string,array<string,mixed>>
     */
    private function take(string $reply, callable $parse, array $chunk): array
    {
        $json = $this->readJson($reply);

        return $json === null ? [] : $parse($json, $chunk);
    }

    /** What an item is called in a message to the model: its name when it has one, otherwise its key. */
    protected function label(array $item, string $key): string
    {
        return (string) ($item['name'] ?? $item['title'] ?? $key);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Cleaning: a model sometimes returns a list where text was asked for, or text where a list was; flatten it
    // rather than crash, and never let a stray type reach the stored document.

    protected function str(mixed $v): string
    {
        if (is_array($v)) {
            $v = implode(' ', array_map(fn ($x) => $this->str($x), array_values($v)));
        }

        return trim((string) preg_replace('/\s+/u', ' ', (string) $v));
    }

    protected function nullable(mixed $v): ?string
    {
        $s = $this->str($v);

        return $s === '' ? null : $s;
    }

    /**
     * A list of non-empty strings, at most `$max` of them.
     *
     * @return array<int,string>
     */
    protected function strings(mixed $v, int $max): array
    {
        if (is_string($v)) {
            $v = [$v];
        }

        return array_slice(array_values(array_filter(array_map(fn ($x) => $this->str($x), (array) $v), fn ($s) => $s !== '')), 0, $max);
    }

    protected function bloom(mixed $v): string
    {
        $b = strtolower($this->str($v));
        $b = $b === 'analyse' ? 'analyze' : $b;

        return in_array($b, self::BLOOM, true) ? $b : 'understand';
    }

    protected function dok(mixed $v): int
    {
        return in_array((int) $v, [1, 2, 3, 4], true) ? (int) $v : 2;
    }

    protected function minutes(mixed $v, int $default = 3, int $max = 60): int
    {
        return min($max, max(1, (int) ($v ?: $default)));
    }

    public static function words(string $s): int
    {
        return count(array_filter(preg_split('/\s+/u', trim($s)) ?: []));
    }

    /**
     * A diagram spec as the model wrote it, kept only if the diagram renderer can draw it; otherwise null. The notes
     * (one explanation per label) are kept as pairs so the assembler can turn each into a numbered, explained part.
     *
     * @return array{0:?array<string,mixed>,1:array<int,array{label:string,text:string}>}
     */
    protected function diagram(mixed $spec, mixed $notes): array
    {
        if (!is_array($spec) || $spec === []) {
            return [null, []];
        }
        $spec['layout'] = strtolower($this->str($spec['layout'] ?? ''));
        $spec['title'] = $this->str($spec['title'] ?? '');
        if (isset($spec['center'])) {
            $spec['center'] = $this->str($spec['center']);
        }
        foreach (['nodes'] as $list) {
            if (isset($spec[$list])) {
                $spec[$list] = array_values(array_filter(array_map(fn ($n) => $this->str(is_array($n) ? ($n['text'] ?? '') : $n), (array) $spec[$list]), fn ($s) => $s !== ''));
            }
        }
        foreach (['left', 'right'] as $side) {
            if (isset($spec[$side]) && is_array($spec[$side])) {
                $spec[$side] = ['heading' => $this->str($spec[$side]['heading'] ?? ''), 'items' => $this->strings($spec[$side]['items'] ?? [], 5)];
            }
        }

        $pairs = [];
        foreach ((array) $notes as $n) {
            if (is_array($n) && $this->str($n['label'] ?? '') !== '' && $this->str($n['text'] ?? '') !== '') {
                $pairs[] = ['label' => $this->str($n['label']), 'text' => $this->str($n['text'])];
            }
        }

        return [\App\Services\StudyDeck\DiagramRenderer::problems($spec) === [] ? $spec : null, $pairs];
    }

    /**
     * The text every prompt ends with: the chapter, the only source of facts.
     *
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     */
    protected function chapterBlock(array $context): string
    {
        return "CHAPTER TEXT (the only source of facts)\n" . $context['ground_truth'];
    }

    /**
     * How much of the model's own rewriting of a sentence counts as the same sentence: the share of the content
     * words of `$a` that also occur in `$b`, 0 to 1. The deck's own measure, so "repeats the explanation" means the
     * same thing in a document as on a slide.
     */
    protected static function overlap(string $a, string $b): float
    {
        return \App\Services\StudyDeck\SlideContentGenerator::overlap($a, $b);
    }

    /**
     * Is this wording one of the listed ideas? The same words, or most of the same content words. Used so a
     * misconception in a document always traces to the concept data and is never the model's own.
     *
     * @param array<int,string> $listed
     */
    public static function traces(string $text, array $listed): bool
    {
        $norm = fn (string $s) => mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s)));
        $t = $norm($text);
        if ($t === '') {
            return false;
        }
        foreach ($listed as $candidate) {
            $c = $norm((string) $candidate);
            if ($c === '') {
                continue;
            }
            if ($t === $c || str_contains($c, $t) || str_contains($t, $c)) {
                return true;
            }
            if (self::overlap($t, $c) >= 0.5 || self::overlap($c, $t) >= 0.5) {
                return true;
            }
        }

        return false;
    }
}
