<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalibrationDueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $overdueItems,
        public readonly array $dueSoonItems,
        public readonly string $appName,
    ) {}

    public function envelope(): Envelope
    {
        $overdueCount  = count($this->overdueItems);
        $dueSoonCount  = count($this->dueSoonItems);

        $subject = $overdueCount > 0
            ? "⚠ {$overdueCount} device(s) OVERDUE for calibration/maintenance"
            : "📅 {$dueSoonCount} device(s) due for calibration/maintenance soon";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.calibration-due');
    }

    public function attachments(): array
    {
        return [];
    }
}
