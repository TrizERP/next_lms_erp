<?php

namespace App\Services\Homework;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Builds the AI-reviewed homework PDF (per-question verdicts + remarks +
 * a summary block) from a Gemini evaluation payload. Mirrors the dompdf
 * usage in App\Http\Controllers\api\AiSopGenerationController::buildSopPdf().
 */
class HomeworkReviewPdfService
{
    /**
     * @param array{overall_score:int,total_questions:int,percentage:float,results:array<int,array<string,mixed>>} $evaluation
     * @param array{title:string,studentName:string,subjectName:string,standardName:string} $meta
     */
    public function generate(array $evaluation, array $meta): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildHtml($evaluation, $meta));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildHtml(array $evaluation, array $meta): string
    {
        $title = e($meta['title'] ?? 'Homework');
        $studentName = e($meta['studentName'] ?? '');
        $subjectName = e($meta['subjectName'] ?? '');
        $standardName = e($meta['standardName'] ?? '');

        $rows = '';
        foreach ($evaluation['results'] as $row) {
            $rows .= $this->buildQuestionBlock($row);
        }

        $score = (int) $evaluation['overall_score'];
        $total = (int) $evaluation['total_questions'];
        $percentage = number_format((float) $evaluation['percentage'], 1);
        $wrong = count(array_filter($evaluation['results'], fn ($r) => $r['status'] === 'wrong'));
        $partial = count(array_filter($evaluation['results'], fn ($r) => $r['status'] === 'partially_correct'));
        $correct = count(array_filter($evaluation['results'], fn ($r) => $r['status'] === 'correct'));

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { color: #111827; font-family: "DejaVu Sans", sans-serif; font-size: 12px; line-height: 1.55; margin: 28px 34px; }
        h1 { border-bottom: 1px solid #d1d5db; color: #061632; font-size: 20px; margin: 0 0 6px; padding-bottom: 10px; }
        .meta { color: #4b5563; font-size: 10px; margin-bottom: 18px; }
        .question { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 12px; padding: 10px 12px; }
        .question-title { font-weight: bold; font-size: 13px; margin-bottom: 6px; }
        .label { color: #4b5563; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 6px; }
        .value { margin: 2px 0 4px; }
        .verdict { font-weight: bold; font-size: 12px; margin-top: 6px; }
        .verdict-correct { color: #059669; }
        .verdict-partial { color: #d97706; }
        .verdict-wrong { color: #dc2626; }
        .remarks { color: #374151; font-style: italic; margin-top: 4px; }
        .summary { border-top: 2px solid #061632; margin-top: 18px; padding-top: 12px; }
        .summary table { width: 100%; border-collapse: collapse; }
        .summary td { padding: 3px 0; font-size: 12px; }
        .summary td.k { color: #4b5563; width: 60%; }
        .summary td.v { font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <h1>AI Homework Evaluation — {$title}</h1>
    <div class="meta">Student: {$studentName} &nbsp;|&nbsp; Subject: {$subjectName} &nbsp;|&nbsp; Standard: {$standardName}</div>
    {$rows}
    <div class="summary">
        <table>
            <tr><td class="k">Total questions</td><td class="v">{$total}</td></tr>
            <tr><td class="k">Correct answers</td><td class="v">{$correct}</td></tr>
            <tr><td class="k">Partially correct answers</td><td class="v">{$partial}</td></tr>
            <tr><td class="k">Wrong answers</td><td class="v">{$wrong}</td></tr>
            <tr><td class="k">AI score</td><td class="v">{$score} / {$total}</td></tr>
            <tr><td class="k">Percentage</td><td class="v">{$percentage}%</td></tr>
        </table>
    </div>
</body>
</html>
HTML;
    }

    private function buildQuestionBlock(array $row): string
    {
        $no = (int) $row['question_no'];
        $question = nl2br(e($row['question']));
        $studentAnswer = nl2br(e($row['student_answer'] !== '' ? $row['student_answer'] : '(no answer given)'));
        $expectedAnswer = nl2br(e($row['expected_answer']));
        $remarks = nl2br(e($row['remarks']));

        [$verdictClass, $verdictLabel] = match ($row['status']) {
            'correct' => ['verdict-correct', '&#10004; Correct'],
            'partially_correct' => ['verdict-partial', '&#9888; Partially Correct'],
            default => ['verdict-wrong', '&#10008; Wrong'],
        };

        return <<<HTML
<div class="question">
    <div class="question-title">Q{$no}. {$question}</div>
    <div class="label">Student answer</div>
    <div class="value">{$studentAnswer}</div>
    <div class="label">Expected answer</div>
    <div class="value">{$expectedAnswer}</div>
    <div class="verdict {$verdictClass}">{$verdictLabel}</div>
    <div class="remarks">Remarks: {$remarks}</div>
</div>
HTML;
    }
}
