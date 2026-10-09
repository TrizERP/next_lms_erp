<?php

namespace Tests\Unit\StudyDeck;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\StudyDeckImages;

/** A tiny synthetic chapter. Subject-neutral on purpose: nothing in the code under test knows these names. */
trait StudyDeckFixture
{
    protected function raw(): array
    {
        $mcq = fn (int $id, int $concept, string $stem, string $correct = 'B', string $bloom = 'Apply') => [
            'id' => $id, 'concept_id' => $concept, 'question_title' => $stem, 'points' => 1, 'question_type' => 'multiple',
            'g_bloom' => $bloom, 'g_difficulty' => 'Medium', 'g_dok' => 2,
            'answer' => json_encode(['item_form' => 'mcq', 'correct_option' => $correct,
                'model_answer' => 'The second choice is right because a model keeps only what the question needs.',
                'options' => [
                    ['label' => 'A', 'text' => 'First choice', 'is_correct' => $correct === 'A'],
                    ['label' => 'B', 'text' => 'Second choice', 'is_correct' => $correct === 'B'],
                ]]),
        ];

        return [
            'chapter' => ['id' => 1, 'chapter_name' => 'Ideas in exploration', 'standard_id' => 9, 'subject_id' => 2, 'standard_name' => '9', 'subject_name' => 'Science', 'sub_institute_id' => 1],
            'ground_truth' => 'A scientific model is a simplified representation of a real system. A model ignores some details on purpose. '
                . 'A map shows roads but ignores trees. A law describes a repeated pattern. A theory explains why patterns occur. '
                . 'Newton gave three laws of motion. The symbol m stands for mass.',
            'learning_objective' => 'Understand models, laws and theories.',
            'intelligence' => [
                ['concept' => ['concept_name' => 'Models', 'definition' => 'A model is a simplified representation.', 'difficulty' => 'Easy'],
                    'misconceptions' => [['misconception' => 'A model is an exact copy', 'correction' => 'A model keeps only needed features.']],
                    'concept_relationships' => [['source_concept' => 'Models', 'target_concept' => 'Ignoring details', 'relation_type' => 'related_to']]],
                ['concept' => ['concept_name' => 'Ignoring details']],
                ['concept' => ['concept_name' => 'Laws']],
                ['concept' => ['concept_name' => 'Theories']],
            ],
            'topics' => [
                ['id' => 10, 'name' => 'Models', 'description' => null, 'estimated_minutes' => 30],
                ['id' => 11, 'name' => 'Laws and theories', 'description' => null, 'estimated_minutes' => 30],
            ],
            'concepts' => [
                ['id' => 1, 'name' => 'Models', 'description' => null, 'definition' => null, 'topic_id' => 10, 'estimated_mastery_minutes' => 10],
                ['id' => 2, 'name' => 'Ignoring details', 'description' => null, 'definition' => null, 'topic_id' => 10, 'estimated_mastery_minutes' => 10],
                ['id' => 3, 'name' => 'Laws', 'description' => null, 'definition' => null, 'topic_id' => 11, 'estimated_mastery_minutes' => 10],
                ['id' => 4, 'name' => 'Theories', 'description' => null, 'definition' => null, 'topic_id' => 11, 'estimated_mastery_minutes' => 10],
            ],
            'prerequisites' => [
                ['concept_id' => 2, 'prerequisite_id' => 1, 'link_type' => 'requires', 'is_gate' => 1, 'reason' => 'Ignoring needs a model first.'],
                ['concept_id' => 4, 'prerequisite_id' => 3, 'link_type' => 'builds_on', 'is_gate' => 0, 'reason' => 'Theories explain laws.'],
            ],
            'baseline' => [['id' => 5, 'content_category' => 'Classroom Presentation', 'description' => '<p>An older deck about models.</p>']],
            'questions' => [
                $mcq(101, 1, 'Which describes a scientific model?'),
                $mcq(102, 1, 'According to the passage, what is a model?'),
                $mcq(103, 2, 'Why does a map ignore trees?', 'B', 'Understand'),
                [
                    'id' => 104, 'concept_id' => 3, 'question_title' => 'What does a law describe?', 'points' => 2, 'question_type' => 'narrative', 'question_format_code' => 'short',
                    'g_bloom' => 'Remember', 'g_difficulty' => 'Easy', 'g_dok' => 1,
                    'answer' => json_encode(['sub_type' => 'Short Answer', 'model_answer' => 'A repeated pattern.']),
                ],
                $mcq(105, 4, 'Based on the above paragraph, why do patterns occur?'),
            ],
        ];
    }

    /** @return array<string,mixed> a valid 7-slide plan for raw() */
    protected function plan(): array
    {
        $s = fn (string $type, string $title, array $concepts, array $taught, array $extra = []) => $extra + [
            'section' => 'Topic', 'slide_type' => $type, 'title' => $title, 'teaches' => 'The learner takes one idea from ' . $title . '.',
            'concept_ids' => $concepts, 'taught_concept_ids' => $taught, 'relationship' => null, 'visual' => null, 'question_ids' => [],
            'h5p_pattern' => null, 'uses_baseline' => 'improved',
        ];

        return [
            'teaching_strategy' => 'Models first, then laws and theories.',
            'baseline_review' => ['weaknesses' => ['thin'], 'improvements' => ['added checks']],
            'slides' => [
                $s('cover', 'Ideas in exploration', [], []),
                $s('concept_intro', 'What a model is', [1], [1], ['visual' => ['required' => true, 'role' => 'object', 'query' => 'scientific model simplified representation', 'purpose' => 'Shows a map as a simplified model'], 'question_ids' => [101]]),
                $s('relationship', 'Why models ignore details', [1, 2], [2], ['question_ids' => [103], 'relationship' => ['from' => 2, 'to' => 1, 'kind' => 'depends_on', 'idea' => 'Ignoring only makes sense for a model.']]),
                $s('concept_intro', 'What a law is', [3], [3]),
                $s('relationship', 'Laws and theories together', [3, 4], [4], ['question_ids' => [104], 'h5p_pattern' => ['type' => 'flashcards', 'reason' => 'A law is a term to recall.']]),
                $s('concept_map', 'How the ideas connect', [], []),
                $s('exit_ticket', 'Exit ticket', [], []),
            ],
        ];
    }

    /**
     * raw()'s plan with one slide that can carry hotspots (a drawn diagram), one that can carry a scenario
     * (an application slide) and, separately, one for a reveal.
     *
     * @return array<string,mixed>
     */
    protected function planWithRoom(): array
    {
        $p = $this->plan();
        $p['slides'][2]['visual'] = ['required' => true, 'role' => 'diagram', 'query' => 'model ignores details', 'purpose' => 'Shows the two ideas a model balances'];
        $p['slides'][2]['diagram'] = ['layout' => 'hub', 'title' => 'What a model keeps and ignores', 'center' => 'A model', 'nodes' => ['Ignore details', 'Keep the question']];
        $p['slides'][4]['slide_type'] = 'application';

        return $p;
    }

    /** What InteractionPlanner is told to reply for planWithRoom(): a hotspot slide, a reveal and a scenario. @return array<string,mixed> */
    protected function interactionReply(array $over = []): array
    {
        $reply = ['slides' => [
            ['n' => 2, 'reason' => 'a model is easily mistaken for an exact copy, so the two are set side by side', 'interaction' => [
                'kind' => 'compare', 'intro' => 'Select each one to see how it differs.',
                'items' => [
                    ['label' => 'A model', 'text' => 'A model is a simplified representation that keeps only what the question needs.'],
                    ['label' => 'An exact copy', 'text' => 'An exact copy would keep every detail, which a model ignores on purpose.'],
                ],
                'wrapup' => 'A model keeps what matters; a copy keeps everything.',
            ]],
            ['n' => 3, 'reason' => 'the diagram has two parts, each worth a sentence', 'interaction' => [
                'kind' => 'hotspots', 'intro' => 'Select each part of the diagram to read about it.',
                'spots' => [
                    ['label' => 'Ignore details', 'text' => 'A map shows roads but ignores trees, because trees do not help you find your way.'],
                    ['label' => 'Keep the question', 'text' => 'A model keeps only what the question needs, so the same system can have different models.'],
                ],
                'wrapup' => 'Now connect these two ideas to the next slide.',
            ]],
            ['n' => 4, 'reason' => 'two ideas that are better discovered than listed', 'interaction' => [
                'kind' => 'reveal', 'intro' => 'Select a card to see what it says.',
                'items' => [
                    ['label' => 'A law', 'text' => 'A law describes a repeated pattern that scientists keep finding.'],
                    ['label' => 'A theory', 'text' => 'A theory explains why a pattern occurs, not only that it does.'],
                ],
                'wrapup' => '',
            ]],
            ['n' => 5, 'reason' => 'a real choice with consequences about how a theory relates to a law', 'interaction' => [
                'kind' => 'scenario',
                'situation' => 'You notice that a ball always falls when you let go of it. A friend asks you what that tells us.',
                'nodes' => [
                    ['id' => 'n1', 'prompt' => 'What do you tell your friend first?', 'choices' => [
                        ['text' => 'It is a repeated pattern, so it can be stated as a law.', 'outcome' => 'Your friend agrees and asks why it happens.', 'why' => 'A law describes a repeated pattern.', 'sound' => true, 'next' => 'n2'],
                        ['text' => 'It happened once, so nothing can be said.', 'outcome' => 'Your friend tries again and sees it fall every time.', 'why' => 'A repeated pattern is exactly what a law describes.', 'sound' => false, 'next' => null],
                    ]],
                    ['id' => 'n2', 'prompt' => 'Your friend asks why it falls. What do you say?', 'choices' => [
                        ['text' => 'A theory explains why the pattern occurs.', 'outcome' => 'You agree to look for an explanation together.', 'why' => 'A theory explains why patterns occur.', 'sound' => true, 'next' => null],
                        ['text' => 'The law already explains why.', 'outcome' => 'Your friend is still puzzled about the reason.', 'why' => 'A law only describes the pattern; a theory explains it.', 'sound' => false, 'next' => null],
                    ]],
                ],
                'conclusion' => 'A law says what happens and a theory says why it happens.',
            ]],
            ['n' => 6, 'reason' => 'the chapter map is a short chain of ideas', 'interaction' => [
                'kind' => 'steps', 'intro' => 'Select each step to see what it adds.',
                'items' => [
                    ['label' => 'Build a model', 'text' => 'A model is a simplified representation of a real system.'],
                    ['label' => 'State a law', 'text' => 'A law describes a repeated pattern that the model helps to see.'],
                    ['label' => 'Give a theory', 'text' => 'A theory explains why the pattern occurs.'],
                ],
                'wrapup' => '',
            ]],
            ['n' => 7, 'reason' => 'three terms the chapter defines', 'interaction' => [
                'kind' => 'match', 'intro' => 'Pair each term with what it means.',
                'pairs' => [
                    ['term' => 'Model', 'meaning' => 'A simplified representation of a real system.'],
                    ['term' => 'Law', 'meaning' => 'Describes a repeated pattern.'],
                    ['term' => 'Theory', 'meaning' => 'Explains why patterns occur.'],
                ],
                'wrapup' => '',
            ]],
        ]];

        return array_replace_recursive($reply, $over);
    }
    /** @return array<string,mixed> */
    protected function slideText(array $over = []): array
    {
        $one = fn (int $n, string $title, string $body, string $bloom, array $extra = []) => $extra + [
            'n' => $n, 'title' => $title, 'body' => $body, 'explanations' => [], 'bullets' => [], 'example' => null, 'misconception' => null,
            'relationship_note' => null, 'key_idea' => null, 'check' => null, 'bloom' => $bloom, 'dok' => 2, 'minutes' => 2,
        ];
        $quick = fn (string $q, string $a) => ['question' => $q, 'answer' => $a];
        $exp = fn (int $id, string $text) => ['concept_id' => $id, 'text' => $text];

        $slides = [
            $one(1, 'Ideas in exploration', 'Today you meet models, laws and theories.', 'remember'),
            $one(2, 'What a model is', '', 'understand', [
                'explanations' => [$exp(1, 'A scientific model is a simplified representation of a real system.')],
                'misconception' => ['wrong_idea' => 'A model is an exact copy.', 'correction' => 'A model keeps only needed features.'],
            ]),
            $one(3, 'Why models ignore details', '', 'analyze', [
                'explanations' => [$exp(2, 'A model ignores some details on purpose.')],
                'example' => 'A map shows roads but ignores trees.',
                'relationship_note' => 'You can only ignore details once you have a model.',
            ]),
            $one(4, 'What a law is', '', 'remember', [
                'explanations' => [$exp(3, 'A law describes a repeated pattern.')],
                'check' => $quick('What does a law describe?', 'A repeated pattern.'),
                'key_idea' => 'A law describes a repeated pattern.',
            ]),
            $one(5, 'Laws and theories together', '', 'apply', [
                'explanations' => [$exp(4, 'A theory explains why patterns occur.')],
                'bullets' => ['Law: what happens', 'Theory: why'],
            ]),
            $one(6, 'How the ideas connect', 'Models help us state laws and build theories.', 'evaluate', ['check' => $quick('Which idea explains why a pattern occurs?', 'A theory.')]),
            $one(7, 'Exit ticket', 'Show what you know.', 'create', ['check' => $quick('What does the symbol m stand for?', 'Mass.')]),
        ];

        foreach ($over as $n => $patch) {
            $slides[$n - 1] = array_merge($slides[$n - 1], $patch);
        }

        return ['slides' => $slides];
    }

    protected function completer(array $replies): Completer
    {
        return new class($replies) implements Completer {
            public array $prompts = [];

            public function __construct(private array $replies)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                $this->prompts[] = $prompt;

                return array_shift($this->replies) ?? '{}';
            }
        };
    }

    /** @param array<string,mixed>|null $image one result; $more adds further ranked candidates */
    protected function imageSearch(?array $image, array $more = []): ConceptImageSearchService
    {
        return new class($image, $more) extends ConceptImageSearchService {
            public function __construct(private ?array $image, private array $more = [])
            {
            }

            public function rankedImagesFor(string $query, int $limit = 5, ?float $minScore = null, ?string $source = null): array
            {
                return array_map(fn ($i) => $i + ['query' => $query], array_values(array_filter(array_merge([$this->image], $this->more))));
            }

            public function available(): bool
            {
                return true;
            }
        };
    }

    /** A picture store with no database: the same picture is the same reference, as the real store behaves. */
    protected function memoryStore(): DiagramImageStore
    {
        return new class implements DiagramImageStore {
            /** @var array<string,int> */
            private array $ids = [];

            public function store(string $bytes, string $mime, ?string $sourceUrl = null): string
            {
                $this->ids[sha1($bytes)] ??= count($this->ids) + 1;

                return StudyDeckImages::ref($this->ids[sha1($bytes)]);
            }
        };
    }

    protected function goodImage(array $over = []): array
    {
        return $over + [
            'url' => 'https://upload.example.org/map.png', 'title' => 'Map as a simplified scientific model', 'tags' => ['map', 'model'],
            'source_url' => 'https://commons.example.org/File:Map.png', 'creator' => 'A. Person', 'license' => 'BY-SA 4.0',
            'attribution' => '"Map" by A. Person is licensed under BY-SA 4.0', 'provider' => 'wikimedia',
        ];
    }

    protected function png(): array
    {
        return ['bytes' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 'mime' => 'image/png', 'width' => 1, 'height' => 1];
    }
}
