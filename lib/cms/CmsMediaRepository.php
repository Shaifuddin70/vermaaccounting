<?php

declare(strict_types=1);

/**
 * Public website images stored under /media/YYYY/MM/ with descriptive, collision-safe names.
 */
final class CmsMediaRepository
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const OVERSIZED_BYTES = 400 * 1024;
    public const WIDTHS = [480, 960, 1600];
    public const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::instance()->pdo();
    }

    public static function root(): string
    {
        return PROJECT_ROOT . '/media';
    }

    public static function ensureRoot(): void
    {
        $root = self::root();
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }
        $guard = $root . '/.htaccess';
        if (!is_file($guard)) {
            file_put_contents($guard, "Options -Indexes -ExecCGI\n"
                . "<FilesMatch \"\\.(php[0-9]?|phtml|phar|pl|py|cgi|sh|html?|svg)$\">\n  Require all denied\n</FilesMatch>\n"
                . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n");
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cms_media WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** @return list<array> */
    public function search(string $q = '', int $limit = 48, int $offset = 0): array
    {
        $sql = 'SELECT * FROM cms_media';
        $params = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $sql .= ' WHERE original_name LIKE ? OR alt LIKE ? OR path LIKE ?';
            $params = [$like, $like, $like];
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset));
        $stmt->execute($params);
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function count(string $q = ''): int
    {
        if ($q === '') {
            return (int) $this->db->query('SELECT COUNT(*) FROM cms_media')->fetchColumn();
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM cms_media WHERE original_name LIKE ? OR alt LIKE ? OR path LIKE ?');
        $stmt->execute([$like, $like, $like]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Store an uploaded image from $_FILES.
     *
     * @param array{name?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    public function upload(array $file, string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed (error code ' . (int) ($file['error'] ?? -1) . ').');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Upload was not received.');
        }
        return $this->storeFile($tmp, (string) ($file['name'] ?? 'image'), $alt, true);
    }

    /** Download a remote image (used by migrations/blog import). */
    public function importUrl(string $url, string $alt = ''): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('Only http(s) image URLs can be imported.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cmsimg');
        $ch = curl_init($url);
        $fh = fopen($tmp, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'VermaCMS/1.0',
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fh);
        if (!$ok || $code >= 400 || filesize($tmp) === 0) {
            @unlink($tmp);
            throw new RuntimeException('Could not download image (' . $code . ').');
        }
        $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image';
        try {
            return $this->storeFile($tmp, $name, $alt, false);
        } finally {
            @unlink($tmp);
        }
    }

    /** Copy an image that already lives inside the project (migrations). */
    public function importLocal(string $file, string $alt = ''): array
    {
        $real = realpath($file);
        if ($real === false || !str_starts_with($real, realpath(PROJECT_ROOT) . '/')) {
            throw new RuntimeException('Image is outside the site.');
        }
        return $this->storeFile($real, basename($real), $alt, false);
    }

    private function storeFile(string $source, string $originalName, string $alt, bool $isUpload): array
    {
        $size = (int) filesize($source);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Images must be smaller than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!isset(self::MIME_EXT[$mime])) {
            throw new RuntimeException('Only JPG, PNG, GIF and WebP images are allowed.');
        }
        $info = @getimagesize($source);
        if (!$info) {
            throw new RuntimeException('The file is not a valid image.');
        }
        self::ensureRoot();
        $ext = self::MIME_EXT[$mime];
        $dir = gmdate('Y') . '/' . gmdate('m');
        $abs = self::root() . '/' . $dir;
        if (!is_dir($abs)) {
            mkdir($abs, 0755, true);
        }
        $base = CmsPath::slugify(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
        $base = substr($base, 0, 80);
        $name = $base . '.' . $ext;
        $n = 1;
        while (is_file($abs . '/' . $name)) {
            $n++;
            $name = $base . '-' . $n . '.' . $ext;
        }
        $dest = $abs . '/' . $name;
        $moved = $isUpload ? move_uploaded_file($source, $dest) : copy($source, $dest);
        if (!$moved) {
            throw new RuntimeException('Could not save the image.');
        }
        @chmod($dest, 0644);
        $relative = $dir . '/' . $name;
        $this->makeVariants($dest, $mime, (int) $info[0]);

        $stmt = $this->db->prepare('INSERT INTO cms_media (path, original_name, mime, width, height, bytes, alt, caption, credit, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$relative, mb_substr($originalName, 0, 255), $mime, (int) $info[0], (int) $info[1], $size, mb_substr($alt, 0, 500), '', '', Auth::check() ? Auth::userId() : null, now_iso()]);
        return $this->find((int) $this->db->lastInsertId());
    }

    /** Resized copies (name-480w.ext …) for srcset. GIFs are left alone to keep animation. */
    private function makeVariants(string $file, string $mime, int $width): void
    {
        if ($mime === 'image/gif' || !function_exists('imagecreatetruecolor')) {
            return;
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if (!$src) {
            return;
        }
        $height = imagesy($src);
        foreach (self::WIDTHS as $target) {
            if ($width <= $target) {
                continue;
            }
            $h = (int) round($height * $target / $width);
            $dst = imagecreatetruecolor($target, $h);
            if ($mime !== 'image/jpeg') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $target, $h, $width, $height);
            $out = self::variantPath($file, $target);
            match ($mime) {
                'image/jpeg' => imagejpeg($dst, $out, 82),
                'image/png' => imagepng($dst, $out, 8),
                'image/webp' => imagewebp($dst, $out, 80),
            };
            imagedestroy($dst);
        }
        imagedestroy($src);
    }

    private static function variantPath(string $file, int $width): string
    {
        $dot = strrpos($file, '.');
        return substr($file, 0, $dot) . '-' . $width . 'w' . substr($file, $dot);
    }

    public function updateMeta(int $id, string $alt, string $caption, string $credit): void
    {
        $this->db->prepare('UPDATE cms_media SET alt = ?, caption = ?, credit = ? WHERE id = ?')
            ->execute([mb_substr($alt, 0, 500), mb_substr($caption, 0, 500), mb_substr($credit, 0, 255), $id]);
    }

    /** Content records that reference this media item. */
    public function usage(int $id): array
    {
        $media = $this->find($id);
        if ($media === null) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT DISTINCT c.id, c.title, c.path FROM cms_contents c LEFT JOIN cms_seo s ON s.content_id = c.id
            WHERE c.featured_media_id = ? OR s.og_image_id = ? OR c.body_html LIKE ?');
        $stmt->execute([$id, $id, '%' . $media['url'] . '%']);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $media = $this->find($id);
        if ($media === null) {
            return;
        }
        $file = self::root() . '/' . $media['path'];
        $real = realpath($file);
        $rootReal = realpath(self::root());
        if ($real !== false && $rootReal !== false && str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR)) {
            @unlink($real);
            foreach (self::WIDTHS as $w) {
                @unlink(self::variantPath($real, $w));
            }
        }
        $this->db->prepare('UPDATE cms_contents SET featured_media_id = NULL WHERE featured_media_id = ?')->execute([$id]);
        $this->db->prepare('UPDATE cms_seo SET og_image_id = NULL WHERE og_image_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM cms_media WHERE id = ?')->execute([$id]);
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['width'] = (int) $row['width'];
        $row['height'] = (int) $row['height'];
        $row['bytes'] = (int) $row['bytes'];
        $row['url'] = '/media/' . $row['path'];
        $srcset = [];
        $abs = self::root() . '/' . $row['path'];
        foreach (self::WIDTHS as $w) {
            if ($row['width'] > $w && is_file(self::variantPath($abs, $w))) {
                $srcset[] = self::variantPath($row['url'], $w) . ' ' . $w . 'w';
            }
        }
        if ($srcset !== []) {
            $srcset[] = $row['url'] . ' ' . $row['width'] . 'w';
        }
        $row['srcset'] = implode(', ', $srcset);
        $row['oversized'] = $row['bytes'] > self::OVERSIZED_BYTES;
        return $row;
    }
}
