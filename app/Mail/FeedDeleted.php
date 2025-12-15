<?php

namespace App\Mail;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedDeleted extends Mailable
{
    use Queueable, SerializesModels;

    public Feed $feed;

    public string $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(Feed $feed, string $reason)
    {
        $this->feed = $feed;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->feed->email],
            subject: 'Feed Monitor Removed',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.feed-deleted',
            text: 'mail.feed-deleted-text',
            with: [
                'feed' => $this->feed,
                'reason' => $this->reason,
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
