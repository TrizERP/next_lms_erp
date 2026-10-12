<?php

namespace App\Services\StudyDeck\Branching;

/**
 * The wording the planner and the writer share, kept in one place so the two
 * stages cannot drift on who they are writing for or what a good screen is.
 */
final class ScenarioPrompts
{
    /** How to pitch the language, from the class the chapter belongs to. */
    public static function audience(?int $level): string
    {
        return match (true) {
            $level === null => 'Students of school age. Use plain language and short sentences; explain any term from the chapter the first time it appears.',
            $level <= 5 => 'Students aged about ' . ($level + 5) . '. Very simple words, short sentences, and everyday situations a child already knows. One idea per sentence.',
            $level <= 8 => 'Students aged about ' . ($level + 5) . '. Simple, concrete language and everyday situations. Explain every term from the chapter the first time it appears.',
            $level <= 10 => 'Students aged about ' . ($level + 5) . '. Clear language; the chapter\'s own terms are expected, each explained in plain words when first used.',
            default => 'Senior school students. Precise language and the chapter\'s own terms, with reasoning shown step by step.',
        };
    }

    /** Rules about wording that apply to every screen the writer produces. */
    public static function style(): string
    {
        return <<<'TXT'
- Speak to the student as "you". Warm, calm, respectful. Never playful, never scolding. No emoji. No exclamation marks.
- Titles are in sentence case (only the first word and proper names start with a capital), 2 to 9 words, and say what the screen is about.
- Keep sentences short: about 20 words at most, one idea each. Prefer a concrete example to an abstract statement.
- Facts, numbers, names and examples come ONLY from the concepts and chapter text you are given. Do not add a number, a date, a person or an experiment that they do not contain.
- Explain WHY, using the chapter's own reasoning. A consequence says what happened, then why it happened.
- A weaker choice is not "wrong": say what it led to, what was missing, and what to reconsider. Never write "Wrong", "Incorrect", "Oops" or "Unfortunately".
- Never refer to "the textbook", "the chapter", "the passage", "the text above" or "as mentioned earlier": the student sees only this scenario.
- Use plain HTML only: <p>, <ul>, <ol>, <li>, <strong>, <em>, <sub>, <sup>. No headings, no links, no styles, no images.
TXT;
    }
}
