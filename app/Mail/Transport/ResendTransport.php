<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class ResendTransport extends AbstractTransport
{
    public function __construct(
        private string $apiKey,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'from' => $this->formatAddress($email->getFrom()[0]),
            'to'   => array_map(fn(Address $a) => $this->formatAddress($a), $email->getTo()),
            'subject' => $email->getSubject(),
        ];

        if ($email->getHtmlBody()) {
            $payload['html'] = $email->getHtmlBody();
        }

        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }

        $cc = $email->getCc();
        if (!empty($cc)) {
            $payload['cc'] = array_map(fn(Address $a) => $this->formatAddress($a), $cc);
        }

        $bcc = $email->getBcc();
        if (!empty($bcc)) {
            $payload['bcc'] = array_map(fn(Address $a) => $this->formatAddress($a), $bcc);
        }

        $replyTo = $email->getReplyTo();
        if (!empty($replyTo)) {
            $payload['reply_to'] = array_map(fn(Address $a) => $this->formatAddress($a), $replyTo);
        }

        // Handle attachments
        $attachments = $email->getAttachments();
        if (!empty($attachments)) {
            $payload['attachments'] = [];
            foreach ($attachments as $attachment) {
                $payload['attachments'][] = [
                    'filename' => $attachment->getFilename(),
                    'content'  => base64_encode($attachment->getBody()),
                ];
            }
        }

        $response = Http::withToken($this->apiKey, 'Bearer')
            ->timeout(30)
            ->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Resend API error: ' . $response->body()
            );
        }
    }

    private function formatAddress(Address $address): string
    {
        if ($address->getName()) {
            return $address->getName() . ' <' . $address->getAddress() . '>';
        }

        return $address->getAddress();
    }

    public function __toString(): string
    {
        return 'resend';
    }
}
