<?php

namespace App\Services\PAL\ApplicationLab;

/**
 * The server's side of each 3D virtual lab: what may be asked, what is marked,
 * and the scientific key the marking is judged against.
 *
 * The front end owns how a lab looks and behaves. The key lives only here so a
 * student never receives the model answer with the page.
 */
class VirtualLabCatalogue
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return self::dissolving() + ['ice_melting' => self::iceMelting()];
    }

    /** @return array<string,array<string,mixed>> */
    private static function dissolving(): array
    {
        return [
            'dissolving' => [
                'id' => 'dissolving',
                'title' => 'The Mechanics of Dissolving: A What and How Investigation',
                'chapter_ids' => [8604],
                'stages' => ['intro', 'predict', 'setup', 'perform', 'compare', 'notebook', 'assess', 'feedback'],
                'prediction_options' => ['cold', 'warm', 'same'],
                'questions' => [
                    ['id' => 'what', 'skill' => 'what', 'type' => 'text', 'prompt' => 'WHAT happened to the sugar when it was added to the water and stirred?'],
                    ['id' => 'how', 'skill' => 'how', 'type' => 'text', 'prompt' => 'HOW did the water particles help to dissolve the sugar?'],
                    ['id' => 'why', 'skill' => 'why', 'type' => 'text', 'prompt' => 'WHY did the sugar dissolve at a different speed in the two beakers?'],
                    ['id' => 'changed', 'skill' => 'variable', 'type' => 'choice', 'prompt' => 'Which variable did you change on purpose?', 'options' => ['temperature', 'sugar', 'water', 'stirring']],
                    ['id' => 'kept', 'skill' => 'variable', 'type' => 'multi', 'prompt' => 'Which things did you keep the same to make it a fair test?', 'options' => ['sugar', 'water', 'stirring', 'temperature']],
                    ['id' => 'evidence', 'skill' => 'evidence', 'type' => 'text', 'prompt' => 'What evidence from your experiment supports your conclusion?'],
                ],
                'rubric' => [
                    ['criterion' => 'Experimental set-up and fair testing', 'max_points' => 20],
                    ['criterion' => 'Observation and data recording', 'max_points' => 20],
                    ['criterion' => 'Scientific vocabulary', 'max_points' => 15],
                    ['criterion' => 'What and How explanations', 'max_points' => 25],
                    ['criterion' => 'Conclusion and evidence', 'max_points' => 20],
                ],
                'vocabulary' => ['solute', 'solvent', 'solution', 'dissolving', 'particles', 'temperature', 'stirring', 'fair test'],
                'marker_brief' => "3D virtual science lab on dissolving",
                'marker_focus' => 'setting up a fair test, honest recording, correct use of the vocabulary, a clear separation of WHAT (observation) from HOW/WHY (particle explanation), and a conclusion backed by their own evidence',
                'table_map' => [
                    'beakers' => ['beaker1', 'beaker2'],
                    'rows' => [0 => ['temp_c', 'water temperature (°C)', 3.0], 3 => ['dissolve_seconds', 'time to dissolve (s)', 0.3]],
                ],
                // Judged against, never shown to the student.
                'teacher_key' => [
                    'A "what" question asks for an observation: the sugar crystals got smaller and disappeared; the water stayed clear and the sugar was spread through it as a solution.',
                    'A "how" question asks for the mechanism: water (the solvent) particles move constantly and collide with the sugar (the solute); they pull sugar particles away from the crystal and the sugar particles spread out between the water particles.',
                    'Warmer water particles move faster and collide with the crystal more often and with more energy, so sugar dissolves faster in warm water. Stirring brings fresh water to the crystal and also speeds dissolving.',
                    'The variable changed is the water temperature. A fair test keeps the amount of sugar, the amount of water and the stirring the same.',
                    'Dissolving is a physical change: the sugar is still there in the solution and has not been destroyed.',
                    'Do not give credit for "the sugar melted" or "the sugar disappeared" as a final explanation: ask for the particle idea.',
                ],
            ],
        ];
    }

    /** The ice-melting mission, merged into all() below. @return array<string,mixed> */
    private static function iceMelting(): array
    {
        return [
            'id' => 'ice_melting',
            'title' => 'Does Temperature Affect How Fast Ice Melts?',
            'chapter_ids' => [8592],
            'stages' => ['intro', 'predict', 'setup', 'perform', 'compare', 'notebook', 'assess', 'feedback'],
            'prediction_options' => ['cool', 'warm', 'same'],
            'questions' => [
                ['id' => 'faster', 'skill' => 'what', 'type' => 'text', 'prompt' => 'Which ice cube melted faster?'],
                ['id' => 'difference', 'skill' => 'what', 'type' => 'text', 'prompt' => 'What difference did you observe between the two beakers?'],
                ['id' => 'how', 'skill' => 'how', 'type' => 'text', 'prompt' => 'How did temperature affect melting?'],
                ['id' => 'prediction', 'skill' => 'evidence', 'type' => 'text', 'prompt' => 'Was your prediction correct? Say how you know.'],
                ['id' => 'evidence', 'skill' => 'evidence', 'type' => 'text', 'prompt' => 'What evidence supports your conclusion?'],
            ],
            'rubric' => [
                ['criterion' => 'Setting up a fair test', 'max_points' => 20],
                ['criterion' => 'Observation and recording', 'max_points' => 20],
                ['criterion' => 'Interpreting the results', 'max_points' => 20],
                ['criterion' => 'Using evidence in reasoning', 'max_points' => 20],
                ['criterion' => 'Scientific conclusion and vocabulary', 'max_points' => 20],
            ],
            'vocabulary' => ['temperature', 'prediction', 'observation', 'evidence', 'fair test', 'conclusion'],
            'marker_brief' => 'ice-melting science mission (does temperature affect how fast ice melts?)',
            'marker_focus' => 'a prediction with a reason, a fair test set up with the right equipment in the right places, careful recording of what was observed, interpreting the results, and a conclusion that uses the student\'s own evidence. The numbers are simulated: if the student treats them as real-world measurements, gently point that out',
            'table_map' => [
                'beakers' => ['beakerA', 'beakerB'],
                'rows' => [0 => ['temp_c', 'temperature (°C)', 3.0], 1 => ['dissolve_seconds', 'melting time (s)', 0.3]],
            ],
            // Judged against, never shown to the student.
            'teacher_key' => [
                'Ice melts faster in the warmer place: heat flows into the ice faster when the surroundings are warmer, so the ice takes in energy faster and changes from solid to liquid sooner.',
                'A fair test changes only one thing (the temperature of the place) and keeps everything else the same: an identical ice cube, the same kind of beaker, started at the same moment.',
                'Evidence means the measurements and observations (the thermometer readings and the melting times), not just a feeling. A good conclusion says what the numbers show and links it to temperature.',
                'A prediction is a reasoned guess made before the experiment. A prediction that turns out different is not a mistake: it is useful evidence.',
                'The data are simulated. A student who writes as if they were real-world measurements should be gently reminded that they came from a simulation.',
                'Credit careful observation words (smaller, clear water, how long) and correct use of: temperature, prediction, observation, evidence, fair test, conclusion.',
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }
}
