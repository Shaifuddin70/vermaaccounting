<?php

declare(strict_types=1);

/**
 * Marketing email unsubscribe helpers (campaigns / holiday / birthday).
 */

function email_unsubscribe_secret(): string
{
    $mail = function_exists('mail_config') ? mail_config() : [];
    $secret = trim((string) ($mail['unsubscribe_secret'] ?? ''));
    if ($secret !== '') {
        return $secret;
    }
    if (function_exists('form_spam_secret')) {
        return form_spam_secret() . '|email-unsub';
    }
    $config = function_exists('app_config') ? app_config() : [];
    return hash('sha256', (string) ($config['admin_password_hash'] ?? 'verma') . '|email-unsub');
}

function email_unsubscribe_token(int $clientId, string $email): string
{
    $clientId = max(0, $clientId);
    $email = strtolower(trim($email));
    $payload = $clientId . '|' . $email;
    $sig = hash_hmac('sha256', $payload, email_unsubscribe_secret());
    $raw = $clientId . '.' . substr($sig, 0, 32);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** @return array{client_id: int, email: string}|null */
function email_unsubscribe_parse_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $b64 = strtr($token, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $raw = base64_decode($b64, true);
    if ($raw === false || !str_contains($raw, '.')) {
        return null;
    }
    [$idPart, $sig] = explode('.', $raw, 2);
    $clientId = (int) $idPart;
    if ($clientId < 1 || !preg_match('/^[a-f0-9]{32}$/i', $sig)) {
        return null;
    }

    $client = (new ClientRepository())->find($clientId);
    if ($client === null) {
        return null;
    }
    $email = strtolower(trim((string) ($client['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $expected = email_unsubscribe_token($clientId, $email);
    if (!hash_equals($expected, $token)) {
        return null;
    }

    return ['client_id' => $clientId, 'email' => $email];
}

function email_unsubscribe_url(int $clientId, string $email): string
{
    $base = rtrim(app_base_url() ?: 'https://vermaaccounting.ca', '/');
    return $base . '/email-unsubscribe?t=' . rawurlencode(email_unsubscribe_token($clientId, $email));
}

function email_unsubscribe_mailto(): string
{
    $mail = mail_config();
    $to = trim((string) (($mail['reply_to'] ?? '') ?: ($mail['admin_emails'][0] ?? $mail['admin_email'] ?? '')));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = trim((string) ($mail['from_email'] ?? ''));
    }
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    return 'mailto:' . $to . '?subject=' . rawurlencode('Unsubscribe');
}

/**
 * @return array{url?: string, mailto?: string}
 */
function email_unsubscribe_header_parts(?int $clientId, string $email): array
{
    $parts = [];
    $email = strtolower(trim($email));
    if ($clientId !== null && $clientId > 0 && $email !== '') {
        $parts['url'] = email_unsubscribe_url($clientId, $email);
    }
    $mailto = email_unsubscribe_mailto();
    if ($mailto !== '') {
        $parts['mailto'] = $mailto;
    }
    return $parts;
}

function email_unsubscribe_footer_html(?string $url): string
{
    if ($url === null || $url === '') {
        return '<p style="margin:16px 0 0;font-size:12px;color:#94a3b8;line-height:1.5;">'
            . 'You received this message from Verma Accounting. '
            . 'If you no longer wish to receive marketing emails, reply with “Unsubscribe”.</p>';
    }
    return '<p style="margin:16px 0 0;font-size:12px;color:#94a3b8;line-height:1.5;">'
        . 'You received this message from Verma Accounting. '
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" style="color:#64748b;">Unsubscribe</a>'
        . ' from marketing emails.</p>';
}

function email_unsubscribe_footer_text(?string $url): string
{
    if ($url === null || $url === '') {
        return "\n\nTo stop marketing emails, reply with Unsubscribe.";
    }
    return "\n\nUnsubscribe from marketing emails: " . $url;
}

function email_default_reply_to(): ?string
{
    $mail = mail_config();
    $reply = trim((string) ($mail['reply_to'] ?? ''));
    if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        return strtolower($reply);
    }
    $admin = trim((string) (($mail['admin_emails'][0] ?? null) ?: ($mail['admin_email'] ?? '')));
    if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) {
        return strtolower($admin);
    }
    return null;
}
