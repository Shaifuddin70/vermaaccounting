<?php

declare(strict_types=1);

/**
 * Optional external SEO data: PageSpeed Insights, Google Search Console and GA4.
 *
 * Credentials live in config.local.php under 'cms' => ['integrations' => [...]]:
 *   'pagespeed_api_key'            => '...',
 *   'google_service_account_file'  => '/absolute/path/outside/webroot/service-account.json',
 *   'gsc_site'                     => 'sc-domain:vermaaccounting.ca' or 'https://vermaaccounting.ca/',
 *   'ga4_property_id'              => '123456789',
 *
 * Every fetched result is stored with its source and fetch time so reports can show where numbers came from.
 */
final class CmsIntegrations
{
    private const CACHE_KEY = 'cms_integration_data';

    public static function config(): array
    {
        $c = app_config()['cms']['integrations'] ?? [];
        return is_array($c) ? $c : [];
    }

    /** @return array<string, array{configured: bool, label: string, help: string}> */
    public static function status(): array
    {
        $c = self::config();
        $sa = (string) ($c['google_service_account_file'] ?? '');
        $saOk = $sa !== '' && is_readable($sa);
        return [
            'pagespeed' => ['configured' => trim((string) ($c['pagespeed_api_key'] ?? '')) !== '', 'label' => 'PageSpeed Insights', 'help' => "Set 'pagespeed_api_key'."],
            'gsc' => ['configured' => $saOk && trim((string) ($c['gsc_site'] ?? '')) !== '', 'label' => 'Google Search Console', 'help' => "Set 'google_service_account_file' and 'gsc_site', and add the service account as a user in Search Console."],
            'ga4' => ['configured' => $saOk && trim((string) ($c['ga4_property_id'] ?? '')) !== '', 'label' => 'Google Analytics 4', 'help' => "Set 'google_service_account_file' and 'ga4_property_id', and give the service account Viewer access to the property."],
        ];
    }

    /** @return array<string, array{fetched_at: string, source: string, data: mixed}> */
    public static function cached(): array
    {
        $raw = (new SettingsRepository())->get(self::CACHE_KEY, []);
        return is_array($raw) ? $raw : [];
    }

    private static function store(string $key, string $source, mixed $data): array
    {
        $all = self::cached();
        $all[$key] = ['fetched_at' => now_iso(), 'source' => $source, 'data' => $data];
        (new SettingsRepository())->set(self::CACHE_KEY, $all);
        return $all[$key];
    }

    /** @return array{fetched_at: string, source: string, data: array} */
    public static function pageSpeed(string $url, string $strategy = 'mobile'): array
    {
        $key = trim((string) (self::config()['pagespeed_api_key'] ?? ''));
        if ($key === '') {
            throw new RuntimeException('PageSpeed Insights is not configured.');
        }
        $strategy = $strategy === 'desktop' ? 'desktop' : 'mobile';
        $endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query(['url' => $url, 'strategy' => $strategy, 'key' => $key, 'category' => 'performance']);
        $res = self::http('GET', $endpoint, null, [], 90);
        $lh = $res['lighthouseResult'] ?? [];
        $field = $res['loadingExperience']['metrics'] ?? [];
        $data = [
            'url' => $url,
            'strategy' => $strategy,
            'score' => isset($lh['categories']['performance']['score']) ? (int) round($lh['categories']['performance']['score'] * 100) : null,
            'lab' => [
                'LCP' => $lh['audits']['largest-contentful-paint']['displayValue'] ?? '',
                'CLS' => $lh['audits']['cumulative-layout-shift']['displayValue'] ?? '',
                'TBT' => $lh['audits']['total-blocking-time']['displayValue'] ?? '',
                'FCP' => $lh['audits']['first-contentful-paint']['displayValue'] ?? '',
            ],
            'field' => [
                'LCP' => $field['LARGEST_CONTENTFUL_PAINT_MS']['category'] ?? '',
                'INP' => $field['INTERACTION_TO_NEXT_PAINT']['category'] ?? '',
                'CLS' => $field['CUMULATIVE_LAYOUT_SHIFT_SCORE']['category'] ?? '',
            ],
        ];
        return self::store('pagespeed:' . $strategy . ':' . $url, 'PageSpeed Insights API (' . $strategy . ')', $data);
    }

    /** Clicks, impressions, CTR and position per page for the last N days. */
    public static function searchConsole(int $days = 28): array
    {
        $site = trim((string) (self::config()['gsc_site'] ?? ''));
        if ($site === '' || !self::status()['gsc']['configured']) {
            throw new RuntimeException('Search Console is not configured.');
        }
        $token = self::googleToken('https://www.googleapis.com/auth/webmasters.readonly');
        $end = gmdate('Y-m-d', strtotime('-2 days'));
        $start = gmdate('Y-m-d', strtotime('-' . ($days + 2) . ' days'));
        $res = self::http('POST', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query', [
            'startDate' => $start, 'endDate' => $end, 'dimensions' => ['page'], 'rowLimit' => 500,
        ], ['Authorization: Bearer ' . $token]);
        $rows = [];
        foreach ($res['rows'] ?? [] as $r) {
            $rows[] = [
                'path' => CmsPath::normalize((string) parse_url((string) ($r['keys'][0] ?? ''), PHP_URL_PATH)),
                'clicks' => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'ctr' => round((float) ($r['ctr'] ?? 0) * 100, 1),
                'position' => round((float) ($r['position'] ?? 0), 1),
            ];
        }
        return self::store('gsc', 'Google Search Console, ' . $start . ' to ' . $end, $rows);
    }

    /** Views and sessions per page path for the last N days. */
    public static function analytics(int $days = 28): array
    {
        $property = preg_replace('/\D/', '', (string) (self::config()['ga4_property_id'] ?? ''));
        if ($property === '' || !self::status()['ga4']['configured']) {
            throw new RuntimeException('Google Analytics is not configured.');
        }
        $token = self::googleToken('https://www.googleapis.com/auth/analytics.readonly');
        $res = self::http('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/' . $property . ':runReport', [
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'yesterday']],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews'], ['name' => 'sessions'], ['name' => 'engagementRate']],
            'limit' => 500,
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        ], ['Authorization: Bearer ' . $token]);
        $rows = [];
        foreach ($res['rows'] ?? [] as $r) {
            $rows[] = [
                'path' => CmsPath::normalize((string) ($r['dimensionValues'][0]['value'] ?? '/')),
                'views' => (int) ($r['metricValues'][0]['value'] ?? 0),
                'sessions' => (int) ($r['metricValues'][1]['value'] ?? 0),
                'engagement' => round((float) ($r['metricValues'][2]['value'] ?? 0) * 100, 1),
            ];
        }
        return self::store('ga4', 'Google Analytics 4 property ' . $property . ', last ' . $days . ' days', $rows);
    }

    private static function googleToken(string $scope): string
    {
        $file = (string) (self::config()['google_service_account_file'] ?? '');
        $sa = json_decode((string) @file_get_contents($file), true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new RuntimeException('The Google service account file could not be read.');
        }
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $now = time();
        $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode([
            'iss' => $sa['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ]));
        $signature = '';
        if (!openssl_sign($header . '.' . $claims, $signature, (string) $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Google token request.');
        }
        $jwt = $header . '.' . $claims . '.' . $b64($signature);
        $res = self::http('POST', 'https://oauth2.googleapis.com/token', null, ['Content-Type: application/x-www-form-urlencoded'], 20,
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
        $token = (string) ($res['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Google did not return an access token.');
        }
        return $token;
    }

    private static function http(string $method, string $url, ?array $json, array $headers = [], int $timeout = 30, ?string $rawBody = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($json);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        } elseif ($rawBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = $rawBody;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException('Request failed: ' . $err);
        }
        $data = json_decode((string) $body, true);
        if ($code >= 400) {
            $msg = is_array($data) ? (string) ($data['error']['message'] ?? $data['error_description'] ?? $data['error'] ?? '') : '';
            throw new RuntimeException('HTTP ' . $code . ($msg !== '' ? ': ' . $msg : ''));
        }
        return is_array($data) ? $data : [];
    }
}
