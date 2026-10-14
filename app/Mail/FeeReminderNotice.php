<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * One student's own fee reminder.
 *
 * Carries a single recipient's message and nothing else. It is its own class — not a reuse of
 * `StudentReportNotice` — because that class declares a readonly `$html`, which the framework's
 * `Mailable` already declares as an ordinary property, so it cannot even be loaded here.
 * The property names below deliberately collide with nothing on `Mailable`.
 */
class FeeReminderNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $messageHtml,
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)->html($this->messageHtml);
    }
}
