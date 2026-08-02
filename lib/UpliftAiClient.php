<?php

declare(strict_types=1);

/**
 * Uplift AI Custom API client (public blogs endpoints).
 * Docs: https://www.upliftai.co/integrations/custom-api
 *
 * Note: the Custom API is read-only. Blogs are authored in Uplift;
 * this client fetches publish-ready posts for your site.
 */
final class UpliftAiClient
{
    private const BASE_URL = 'https://api.upliftai.co/api/public/v1';

    private string $token;

    public function __construct(?string $token = null)
    {
        $config = app_config();
        $this->token = trim((string) ($token ?? ($config['uplift_ai']['api_token'] ?? '')));
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /**
     * @return array{blogs: list<array<string, mixed>>, pagination: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function listBlogs(int $page = 1, int $limit = 50, string $status = 'ALL'): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $status = strtoupper($status);
        if (!in_array($status, ['PUBLISH', 'DRAFT', 'ALL'], true)) {
            $status = 'ALL';
        }

        $query = http_build_query([
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
        ]);

        $payload = $this->request('GET', '/blogs?' . $query);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $blogs = is_array($data['blogs'] ?? null) ? array_values($data['blogs']) : [];
        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];

        return [
            'blogs' => $blogs,
            'pagination' => [
                'page' => (int) ($pagination['page'] ?? $page),
                'limit' => (int) ($pagination['limit'] ?? $limit),
                'total' => (int) ($pagination['total'] ?? count($blogs)),
                'totalPages' => (int) ($pagination['totalPages'] ?? 1),
            ],
        ];
    }

    /**
     * Fetch every blog across pages.
     *
     * @return list<array<string, mixed>>
     */
    public function listAllBlogs(string $status = 'ALL', int $limit = 100): array
    {
        $all = [];
        $page = 1;
        $totalPages = 1;

        do {
            $result = $this->listBlogs($page, $limit, $status);
            foreach ($result['blogs'] as $blog) {
                if (is_array($blog)) {
                    $all[] = $blog;
                }
            }
            $totalPages = max(1, (int) $result['pagination']['totalPages']);
            $page++;
        } while ($page <= $totalPages);

        return $all;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Uplift AI API token is not configured.');
        }

        $url = self::BASE_URL . $path;
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Uplift AI sync.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTP client for Uplift AI.');
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno !== 0) {
            throw new RuntimeException('Uplift AI request failed: ' . $error);
        }
        if (!is_string($body)) {
            throw new RuntimeException('Uplift AI returned an empty response.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Uplift AI returned invalid JSON (HTTP ' . $status . ').');
        }

        if ($status >= 400 || empty($decoded['success'])) {
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $status));
            throw new RuntimeException('Uplift AI API error: ' . $message);
        }

        return $decoded;
    }
}
