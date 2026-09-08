<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * One person's own figures, taken from a generated report.
 *
 * Deliberately carries a single recipient's notice rather than the report it came
 * from. A consolidated arrears report names every family that owes money, so mailing
 * *it* to each of them would disclose all of their debts to all of them — the kind of
 * mistake that is one loop away in an obvious implementation, and the reason
 * `ReportSender` composes per recipient instead of attaching the document.
 */
class StudentReportNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $html
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)->html($this->html);
    }
}
