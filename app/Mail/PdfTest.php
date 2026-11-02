<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A Rodeo PDF template sent as a test: a short note with the PDF attached. */
class PdfTest extends Mailable
{
    public function __construct(public string $subjectLine, public string $templateName, public ?string $account, public string $fileName, public string $pdf, public string $sentBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p style="font-family:Arial,sans-serif">This is a <b>test</b> of the PDF template <b>'.e($this->templateName).'</b>'
            .($this->account ? ', filled in for sample account '.e($this->account) : '').'. The PDF is attached.</p>'
            .'<p style="font-family:Arial,sans-serif;color:#667">Sent by '.e($this->sentBy).' from Rodeo → PDF Templates → Send a Test.</p>');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->fileName)->withMime('application/pdf')];
    }
}
