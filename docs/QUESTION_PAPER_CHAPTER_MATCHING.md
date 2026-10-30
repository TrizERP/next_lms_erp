# Question paper chapter matching: how thresholds were chosen

A paper is never assumed to be about one chapter. Each question is ranked against the chapters of the resolved standard
and subject only (`QuestionPaperChapterMatcher`), using the school's own curriculum text for those chapters: title, key
concepts, description, topics, concepts, learning outcomes and semantic intelligence (`ChapterTextScorer`: TF-IDF cosine
over unigrams and bigrams, no AI call).

A chapter is **assigned** only when the best chapter has a cosine score of at least `MIN_SCORE` (0.06) and leads the
runner-up by at least `MIN_MARGIN_RATIO` (25% of its own score). Anything weaker is a **suggestion**, shown with its score,
the matching terms and the closest alternatives, and is never saved as the question's chapter until a teacher confirms it.

## Measurement (read-only, 2026-10-29)

Data: Standard 9 (id 42) Science (subject 3975) of school 1: 13 chapters; 3,134 existing bank questions with a recorded
chapter. The matcher ranked each question (stem + options) and the top chapter was compared with the recorded one.

| Group | Questions | Top-1 accuracy |
|---|---|---|
| Extracted from textbooks (the realistic case) | 2,346 | 94.2% |
| AI-generated | 788 | 14.0% (their recorded chapters do not follow the chapter text; not used to set thresholds) |

Auto-assignment on the extracted group, precision / coverage:

| min score | margin ratio >= 0.15 | >= 0.25 | >= 0.35 |
|---|---|---|---|
| 0.04 | 97.8% / 89.7% | 98.8% / 85.0% | 99.4% / 78.7% |
| **0.06** | 98.5% / 77.4% | **99.4% / 74.3%** | 99.6% / 69.9% |
| 0.08 | 98.9% / 57.0% | 99.5% / 55.4% | 99.7% / 52.6% |

The chosen row is bold: a wrong automatic assignment costs more than leaving a question for a teacher.

## Caveats

- The recorded chapters of the extracted questions come from the same textbooks that populate the chapter text, so real
  exam papers written by other authors may score lower. The thresholds are deliberately conservative, and lower scores
  fall back to suggestions, not to wrong assignments.
- Calibrated on one standard/subject. Re-measure before relying on it for a very different subject (for example a language).
