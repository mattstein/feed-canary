<?php

namespace App\Mail;

use App\Models\Check;
use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedFailed extends Mailable
{
    use Queueable, SerializesModels;

    public Feed $feed;

    public Check $check;

    /**
     * Create a new message instance.
     */
    public function __construct(Feed $feed, Check $check)
    {
        $this->feed = $feed;
        $this->check = $check;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->feed->email],
            subject: 'Feed Failed',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.feed-failed',
            text: 'mail.feed-failed-text',
            with: [
                'feed' => $this->feed,
                'check' => $this->check,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
