<?php

namespace App\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class UnsendTransport extends AbstractTransport
{
    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $apiKey = env('UNSEND_API_KEY');
        $baseUrl = Uri::to('/')
            ->withHost(env('UNSEND_DOMAIN'))
            ->withScheme('https')
            ->value();

        if ($from = collect($email->getFrom())->first()) {
            $fromValue = $from->toString();
        }

        $postBody = [
            'to' => collect($email->getTo())->map(function (Address $email) {
                return $email->toString();
            })->toArray(),
            'from' => $fromValue ?? null,
            'subject' => $email->getSubject(),
            'replyTo' => collect($email->getReplyTo())->map(function (Address $email) {
                return $email->getAddress();
            })->toArray(),
            'cc' => collect($email->getCc())->map(function (Address $email) {
                return $email->toString();
            })->toArray(),
            'bcc' => collect($email->getBcc())->map(function (Address $email) {
                return $email->toString();
            })->toArray(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            // TODO: support attachments
            // 'attachments' => '',
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ])
            ->baseUrl($baseUrl)
            ->post(
                '/api/v1/emails',
                $postBody
            );
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'unsend';
    }
}
