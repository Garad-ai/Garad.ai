<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Gmail;

use Google\Service\Gmail\Message;

class MimeMessageBuilder
{
    /**
     * Build a raw MIME message for sending.
     */
    public static function build(string $to, string $subject, string $body, ?string $from = null, ?string $inReplyTo = null, ?string $threadId = null): Message
    {
        $headers = [];

        if ($from) {
            $headers[] = 'From: ' . $from;
        }

        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: quoted-printable';

        if ($inReplyTo) {
            $headers[] = 'In-Reply-To: ' . $inReplyTo;
            $headers[] = 'References: ' . $inReplyTo;
        }

        $raw = implode("\r\n", $headers) . "\r\n\r\n" . quoted_printable_encode($body);

        $message = new Message();
        $message->setRaw(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='));

        if ($threadId) {
            $message->setThreadId($threadId);
        }

        return $message;
    }
}
