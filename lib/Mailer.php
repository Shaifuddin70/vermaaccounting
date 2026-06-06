<?php

declare(strict_types=1);

final class Mailer
{
    private string $fromEmail;
    private string $fromName;
    private string $transport;
    /** @var array<string, mixed> */
    private array $smtp;
    private string $lastError = '';

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->fromEmail = trim((string) ($config['from_email'] ?? ''));
        $this->fromName = trim((string) ($config['from_name'] ?? 'Verma Accounting'));
        $this->transport = strtolower(trim((string) ($config['transport'] ?? 'mail')));
        $this->smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
    }

    public static function fromAppConfig(): ?self
    {
        $mail = mail_config();
        if (empty($mail['enabled']) || ($mail['from_email'] ?? '') === '') {
            return null;
        }
        return new self($mail);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null, ?string $replyTo = null): bool
    {
        $this->lastError = '';
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Invalid recipient email address.';
            return false;
        }
        if ($this->fromEmail === '' || !filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Invalid From email address in config.';
            return false;
        }

        $textBody = $textBody ?? $this->htmlToText($htmlBody);
        $boundary = 'va_' . bin2hex(random_bytes(12));
        $headers = $this->buildHeaders($to, $boundary, $replyTo);
        $body = $this->buildMultipartBody($boundary, $textBody, $htmlBody);
        $encodedSubject = $this->encodeHeader($subject);

        if ($this->transport === 'smtp') {
            return $this->sendViaSmtp($to, $encodedSubject, $headers, $body);
        }

        return $this->sendViaMail($to, $encodedSubject, $headers, $body);
    }

    private function sendViaMail(string $to, string $subject, string $headers, string $body): bool
    {
        $params = '-f ' . escapeshellarg($this->fromEmail);
        $ok = @mail($to, $subject, $body, $headers, $params);
        if (!$ok) {
            $this->lastError = 'PHP mail() failed. On shared hosting this usually only works when the site runs on the server, not from local MAMP.';
        }
        return $ok;
    }

    private function sendViaSmtp(string $to, string $subject, string $headers, string $body): bool
    {
        $host = trim((string) ($this->smtp['host'] ?? ''));
        $port = (int) ($this->smtp['port'] ?? 587);
        $encryption = strtolower(trim((string) ($this->smtp['encryption'] ?? 'tls')));
        $username = trim((string) ($this->smtp['username'] ?? ''));
        $password = (string) ($this->smtp['password'] ?? '');

        if ($host === '') {
            return false;
        }

        $remote = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @fsockopen($remote, $port, $errno, $errstr, 20);
        if (!$socket) {
            $this->lastError = 'Could not connect to SMTP server ' . $host . ':' . $port . ' — ' . $errstr
                . '. Add a DNS A record for mail.yourdomain.ca or use the server hostname from hosting.';
            error_log('Mailer SMTP connect failed: ' . $this->lastError);
            return false;
        }

        stream_set_timeout($socket, 20);

        try {
            $ehloHost = $this->smtpEhloHost();
            $this->smtpExpect($socket, [220]);
            $this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250]);

            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    throw new RuntimeException('STARTTLS failed');
                }
                $this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250]);
            }

            if ($username !== '' && $password !== '') {
                $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->smtpCommand($socket, base64_encode($username), [334]);
                $this->smtpCommand($socket, base64_encode($password), [235]);
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $this->fromEmail . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);
            $this->smtpSendData($socket, $subject, $headers, $body);
            $this->smtpExpect($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Mailer SMTP error: ' . $this->lastError);
            fclose($socket);
            return false;
        }
    }

    /** @param resource $socket */
    private function smtpSendData($socket, string $subject, string $headers, string $body): void
    {
        $lines = explode("\n", str_replace("\r\n", "\n", 'Subject: ' . $subject . "\n" . $headers . "\n\n" . $body));
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            fwrite($socket, $line . "\r\n");
        }
        fwrite($socket, ".\r\n");
    }

    private function smtpEhloHost(): string
    {
        $host = trim((string) ($this->smtp['host'] ?? ''));
        if ($host !== '' && !filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        if (str_contains($this->fromEmail, '@')) {
            return substr($this->fromEmail, strpos($this->fromEmail, '@') + 1);
        }
        return 'localhost';
    }

    /** @param resource $socket */
    private function smtpCommand($socket, string $command, array $okCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->smtpExpect($socket, $okCodes);
    }

    /** @param resource $socket */
    private function smtpExpect($socket, array $okCodes): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim(preg_replace('/\s+/', ' ', $response) ?? $response));
        }
    }

    private function buildHeaders(string $to, string $boundary, ?string $replyTo): string
    {
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'MIME-Version: 1.0',
            'From: ' . $this->formatAddress($this->fromEmail, $this->fromName),
            'To: ' . $to,
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->smtpEhloHost() . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $headers);
    }

    private function buildMultipartBody(string $boundary, string $textBody, string $htmlBody): string
    {
        return "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $textBody . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--{$boundary}--";
    }

    private function formatAddress(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
