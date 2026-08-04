<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdministratorAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $administrator,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your administrative account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.administrator-account-created');
    }
}
