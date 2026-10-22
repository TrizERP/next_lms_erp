<?php

namespace App\Services\CoursePresentation;

/**
 * The two choices on the Course Presentation card, and what the existing generators can honestly do with them.
 * Reads config only.
 */
class Options
{
    /** @return array<string,string> key => label */
    public function pedagogies(): array
    {
        return (array) config('course_presentation_pilot.pedagogies', []);
    }

    /** @return array<string,array{label:string,generator:?string,message:string}> */
    public function formats(): array
    {
        return (array) config('course_presentation_pilot.formats', []);
    }

    /** What the screen needs to draw the two choices. */
    public function forScreen(): array
    {
        return [
            'pedagogies' => array_map(
                fn (string $key, string $label) => ['key' => $key, 'label' => $label],
                array_keys($this->pedagogies()),
                array_values($this->pedagogies())
            ),
            'formats' => array_map(
                fn (string $key, array $f) => ['key' => $key, 'label' => $f['label'], 'generator_exists' => $f['generator'] !== null],
                array_keys($this->formats()),
                array_values($this->formats())
            ),
            'pedagogy_note' => '',
        ];
    }

    /**
     * What the existing pipeline can do with this format: whether a generator exists, whether this application can start
     * it, and whether it takes the teaching method as an input. Never "ready" for a format with no generator.
     *
     * @return array{status:string,generation_ready:bool,pedagogy_applied:bool,messages:array<int,string>}
     */
    public function capability(string $pedagogy, string $format): array
    {
        $f = $this->formats()[$format] ?? null;
        if ($f === null) {
            return ['status' => 'unsupported_format', 'generation_ready' => false, 'pedagogy_applied' => false, 'messages' => ['This format is not recognised.']];
        }
        $cap = (array) config("course_presentation_pilot.format_capability.$format", []);
        $hasGenerator = $f['generator'] !== null;
        $trigger = $hasGenerator && !empty($cap['trigger']);
        $applied = $hasGenerator && !empty($cap['pedagogy_applied']);

        $messages = [(string) $f['message']];
        if ($hasGenerator && !$applied) {
            $messages[] = 'This generator takes no teaching method as an input, so the choice would be recorded but would not change what is generated.';
        }
        if ($hasGenerator && !$trigger) {
            $messages[] = (string) ($cap['pending_note'] ?? 'No application endpoint starts this generator; it is run from the command line by an administrator.');
        }

        return [
            'status' => !$hasGenerator ? 'unsupported_format' : ($trigger ? 'generator_available' : 'generator_exists_offline'),
            'generation_ready' => $trigger && $applied,
            'pedagogy_applied' => $applied,
            'messages' => $messages,
        ];
    }
}