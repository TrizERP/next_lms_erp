<?php

namespace App\CareerIntelligence;

/**
 * Knowledge-Based Career Recommendation Engine — narrative layer.
 *
 * Every sentence here is either a fixed, occupation-agnostic template (the
 * three band-case lines, and the neutral "stronger alignment elsewhere"
 * guidance line — wording the counsellor UI's own spec fixes verbatim) or a
 * template interpolated with real values the caller passed in (occupation
 * names, knowledge names, percentages). Nothing here ever says an
 * aspiration is "wrong" — only that other occupations currently show
 * stronger alignment, per the product rule this class exists to enforce.
 */
class CareerRecommendationExplainer
{
    public function alignmentSummary(string $band): string
    {
        return match ($band) {
            AlignmentBandClassifier::STRONG =>
                'Student\'s demonstrated knowledge aligns strongly with the selected aspiration.',
            AlignmentBandClassifier::PARTIAL =>
                'Student shows moderate alignment with the selected aspiration. '
                    .'Additional knowledge development may improve readiness.',
            default =>
                'Student\'s demonstrated knowledge currently shows stronger alignment with other occupations.',
        };
    }

    /** @param array $relatedCareers relatedCareersWithBetterAlignment entries */
    public function relatedCareersGuidance(array $relatedCareers): ?string
    {
        if (empty($relatedCareers)) {
            return null;
        }

        return 'Based on currently demonstrated knowledge, the following occupations show stronger alignment.';
    }

    /** @param array $knowledgeDevelopmentAreas knowledge/importance/level entries */
    public function knowledgeDevelopmentIntro(string $aspirationName, array $knowledgeDevelopmentAreas): ?string
    {
        if (empty($knowledgeDevelopmentAreas)) {
            return null;
        }

        return sprintf('To improve alignment with %s, the student should develop:', $aspirationName);
    }
}
