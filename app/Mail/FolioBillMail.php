<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Tenancy\TenantContext;
use App\Models\Folio;
use App\Models\Hotel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class FolioBillMail extends Mailable
{
    use Queueable, SerializesModels;

    public Hotel $hotel;

    public function __construct(public Folio $folio)
    {
        $this->hotel = app(TenantContext::class)->current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Bill :ref — :hotel', [
                'ref' => $this->folio->reference,
                'hotel' => $this->hotel->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.folios.bill',
            with: [
                'folio' => $this->folio,
                'hotel' => $this->hotel,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
