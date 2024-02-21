<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BrokenLinksNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $brokenLinks;
    public $otherErrors;

    public function __construct($data)
    {
        $this->brokenLinks = $data['brokenLinks'];
        $this->otherErrors = $data['otherErrors'];
    }

    public function build()
    {
        return $this->view('email.broken_links_notification')
            ->with([
                'brokenLinks' => $this->brokenLinks,
                'otherErrors' => $this->otherErrors,
            ]);
    }
}
