<?php

declare(strict_types=1);

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $path = DATA_DIR . '/forms.sqlite';
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS forms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT DEFAULT "",
                schema_json TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "draft",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                form_id INTEGER NOT NULL,
                data_json TEXT NOT NULL,
                ip TEXT,
                user_agent TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS submission_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_id INTEGER NOT NULL,
                field_id TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                original_name TEXT NOT NULL,
                mime TEXT,
                size INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
            );
        ');
    }
}
