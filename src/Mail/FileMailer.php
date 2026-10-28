<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Clock;
use RuntimeException;

/**
 * Development mailer: instead of sending, every message is written to
 * `storage/mail/*.eml` so you can open it in any mail client or text editor.
 * Swap this class for an SMTP/API mailer in production.
 */
final class FileMailer
{
    public function __construct(
        private readonly string $directory,
        private readonly string $fromAddress,
        private readonly Clock $clock,
    ) {
    }

    /** @return string path of the written message */
    public function send(string $to, string $subject, string $body): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Unable to create mail directory: {$this->directory}");
        }

        $now = $this->clock->now();
        // Microsecond timestamps keep the files in chronological order.
        $file = sprintf('%s/%s-%s.eml', $this->directory, $now->format('Ymd-His-u'), bin2hex(random_bytes(3)));

        $message = implode("\r\n", [
            'Date: ' . $now->format(DATE_RFC2822),
            'From: ' . $this->fromAddress,
            'To: ' . $this->sanitizeHeader($to),
            'Subject: ' . $this->sanitizeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            '',
            $body,
        ]);

        file_put_contents($file, $message);

        return $file;
    }

    /** Prevents header injection through user-supplied values. */
    private function sanitizeHeader(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
