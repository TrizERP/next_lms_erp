<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Contracts\Completer;

/**
 * Strict review of which found pictures, if any, serve the slide they were
 * found for. One model call per slide, over all of its candidates.
 *
 * It sees each picture's own title, tags, creator and provider and the slide's
 * stated purpose - text only, never pixels - so it can reject a face mask for a
 * "branches of science" slide but cannot confirm what is actually drawn. The
 * accepted candidates come back in order of preference with a reason each, and
 * the winner's reason is stored on its image record; the source page stays there
 * for the human check that remains necessary before publishing.
 *
 * Fails closed: an unreadable reply accepts nothing.
 */
class ImageRelevanceJudge
{
    use ExtractsJson;

    public function __construct(private readonly Completer $completer)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $candidates Openverse results (title, tags, creator, provider)
     * @return array{accepted:array<int,int>, reasons:array<int,string>, reason:string}
     */
    public function __invoke(string $query, string $purpose, array $candidates, string $teaches = ''): array
    {
        $list = [];
        foreach (array_values($candidates) as $i => $c) {
            $list[] = [
                'index' => $i,
                'title' => $c['title'] ?? null,
                'tags' => $c['tags'] ?? [],
                'creator' => $c['creator'] ?? null,
                'provider' => $c['provider'] ?? null,
            ];
        }
        $facts = json_encode(['slide_teaches' => $teaches, 'slide_needs' => $purpose, 'search_query' => $query, 'candidates' => $list], JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
A classroom slide needs one picture. For each candidate, decide from its own title and tags whether it would clearly serve the slide.

Be strict. Reject a candidate when its title or tags point to a different subject, to a person or portrait where an object or diagram is needed, to an unrelated sense of a shared word, or to something only loosely connected. Accept only when the title and tags indicate it shows what the slide needs AND seeing it helps the learner understand what the slide teaches. A picture of something that is merely an example, a prop or a setting for the idea (an object the text only mentions in passing), or any photograph for an abstract idea, does not teach it: reject it. Accepting none is a normal, correct answer.

Reply with JSON only:
{"accepted": [indexes of acceptable candidates, best first], "reasons": {"<index>": "one short sentence"}, "reason": "if none accepted, one short sentence why"}

{$facts}
PROMPT;

        try {
            $json = $this->extractJson($this->completer->complete('You review image choices for classroom slides. Reply with one JSON object only.', $prompt, 1500));

            $accepted = array_values(array_unique(array_filter(
                array_map('intval', (array) ($json['accepted'] ?? [])),
                fn ($i) => $i >= 0 && $i < count($candidates)
            )));
            $reasons = [];
            foreach ((array) ($json['reasons'] ?? []) as $k => $v) {
                $reasons[(int) $k] = trim(is_array($v) ? implode(' ', $v) : (string) $v);
            }

            return ['accepted' => $accepted, 'reasons' => $reasons, 'reason' => trim((string) ($json['reason'] ?? ''))];
        } catch (\Throwable $e) {
            return ['accepted' => [], 'reasons' => [], 'reason' => 'review could not be read'];
        }
    }
}
