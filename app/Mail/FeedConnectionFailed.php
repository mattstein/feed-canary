<?php

namespace App\Mail;

use App\Models\ConnectionFailure;
use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedConnectionFailed extends Mailable
{
    use Queueable, SerializesModels;

    public Feed $feed;

    public ConnectionFailure $failure;

    /**
     * Create a new message instance.
     */
    public function __construct(Feed $feed, ConnectionFailure $failure)
    {
        $this->feed = $feed;
        $this->failure = $failure;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Feed Connection Failed',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.feed-connection-failed',
            text: 'mail.feed-connection-failed-text',
            with: [
                'feed' => $this->feed,
                'failure' => $this->failure,
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
