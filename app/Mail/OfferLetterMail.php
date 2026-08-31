<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

/**
 * Ported verbatim from hp_erp (G2G). Constructor signature already matches
 * the call site in OfferController::store():
 * `new OfferLetterMail($offer, $pdfPath)`.
 */
class OfferLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public $offer;
    public $attachmentPath;

    public function __construct($offer, $attachmentPath = null)
    {
        $this->offer = $offer;
        $this->attachmentPath = $attachmentPath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Talent Offer Letter',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.offer_letter',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        if ($this->attachmentPath) {
            $attachments[] = Attachment::fromPath($this->attachmentPath)->as('offer_letter.pdf');
        }
        return $attachments;
    }
}
