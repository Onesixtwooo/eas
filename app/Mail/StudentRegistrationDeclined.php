<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentRegistrationDeclined extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $student) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Student registration declined');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-registration-declined');
    }
}
