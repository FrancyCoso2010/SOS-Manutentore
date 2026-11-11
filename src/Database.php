<?php

declare(strict_types=1);

/**
 * Connessione al database tramite PDO (SQLite o MySQL) e schema.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** @var array<string, mixed> */
    private static array $config = [];

    /**
     * Inizializza la connessione e crea lo schema se manca.
     *
     * @param array<string, mixed> $config
     */
    public static function init(array $config): void
    {
        self::$config = $config;
        self::migrate();
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    private static function connect(): PDO
    {
        $config = self::$config;

        if (($config['driver'] ?? 'sqlite') === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                (int) $config['port'],
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );

            return new PDO($dsn, (string) $config['username'], (string) $config['password'], self::options());
        }

        $path = (string) $config['sqlite_path'];
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossibile creare la cartella del database: ' . $directory);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, self::options());
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /**
     * @return array<int, mixed>
     */
    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    private static function migrate(): void
    {
        $pdo = self::pdo();
        $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        if ($isMysql) {
            self::migrateMysql($pdo);
        } else {
            self::migrateSqlite($pdo);
        }

        // Database creati prima dell'introduzione del cambio password obbligatorio.
        if (!self::hasColumn($pdo, 'users', 'must_change_password', $isMysql)) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN must_change_password '
                . ($isMysql ? 'TINYINT(1) NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1')
            );
        }

        // Database creati prima dell'introduzione dei temi per account.
        if (!self::hasColumn($pdo, 'users', 'theme', $isMysql)) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN theme '
                . ($isMysql ? "VARCHAR(20) NOT NULL DEFAULT 'chiaro'" : "TEXT NOT NULL DEFAULT 'chiaro'")
            );
        }

        self::seedDefaultAdmin($pdo);
        self::seedDefaultCategories($pdo);
    }

    private static function migrateSqlite(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL DEFAULT '',
                role TEXT NOT NULL DEFAULT 'operatore',
                is_active INTEGER NOT NULL DEFAULT 1,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                theme TEXT NOT NULL DEFAULT 'chiaro',
                created_at TEXT NOT NULL,
                last_login_at TEXT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                setting_key TEXT NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL DEFAULT '',
                updated_at TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                category_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                category_id INTEGER NULL,
                asset_id INTEGER NULL,
                urgency TEXT NOT NULL DEFAULT 'media',
                status TEXT NOT NULL DEFAULT 'aperto',
                created_by INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                taken_by INTEGER NULL,
                taken_at TEXT NULL,
                resolved_by INTEGER NULL,
                resolved_at TEXT NULL,
                resolution_note TEXT NULL
            )
        SQL);

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_status ON reports (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports (created_at)');

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                frequency_days INTEGER NOT NULL DEFAULT 14,
                next_due_at TEXT NOT NULL,
                last_done_at TEXT NULL,
                last_done_by INTEGER NULL,
                created_by INTEGER NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS task_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                done_by INTEGER NOT NULL,
                done_at TEXT NOT NULL,
                note TEXT NOT NULL DEFAULT ''
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                username TEXT NOT NULL DEFAULT '',
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL DEFAULT '',
                entity_id INTEGER NULL,
                details TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL
            )
        SQL);
    }

    private static function migrateMysql(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(120) NOT NULL DEFAULT '',
                role VARCHAR(30) NOT NULL DEFAULT 'operatore',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                theme VARCHAR(20) NOT NULL DEFAULT 'chiaro',
                created_at DATETIME NOT NULL,
                last_login_at DATETIME NULL,
                UNIQUE KEY uniq_users_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_categories_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS assets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                category_id INT UNSIGNED NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS reports (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(160) NOT NULL,
                description TEXT NOT NULL,
                category_id INT UNSIGNED NULL,
                asset_id INT UNSIGNED NULL,
                urgency VARCHAR(20) NOT NULL DEFAULT 'media',
                status VARCHAR(20) NOT NULL DEFAULT 'aperto',
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                taken_by INT UNSIGNED NULL,
                taken_at DATETIME NULL,
                resolved_by INT UNSIGNED NULL,
                resolved_at DATETIME NULL,
                resolution_note TEXT NULL,
                KEY idx_reports_status (status),
                KEY idx_reports_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tasks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(160) NOT NULL,
                description TEXT NOT NULL,
                frequency_days INT NOT NULL DEFAULT 14,
                next_due_at DATE NOT NULL,
                last_done_at DATETIME NULL,
                last_done_by INT UNSIGNED NULL,
                created_by INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS task_logs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                task_id INT UNSIGNED NOT NULL,
                done_by INT UNSIGNED NOT NULL,
                done_at DATETIME NOT NULL,
                note TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS activity_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                username VARCHAR(50) NOT NULL DEFAULT '',
                action VARCHAR(60) NOT NULL,
                entity_type VARCHAR(40) NOT NULL DEFAULT '',
                entity_id INT UNSIGNED NULL,
                details TEXT NOT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    private static function hasColumn(PDO $pdo, string $table, string $column, bool $isMysql): bool
    {
        if ($isMysql) {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->query('PRAGMA table_info(' . $table . ')');

        foreach ($statement->fetchAll() as $row) {
            if (strcasecmp((string) $row['name'], $column) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inserisce l'utente admin/admin solo se la tabella è vuota.
     * Il primo accesso obbliga a cambiare la password.
     */
    private static function seedDefaultAdmin(PDO $pdo): void
    {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        if ($total > 0) {
            return;
        }

        $pdo->prepare(
            'INSERT INTO users (username, password_hash, full_name, role, is_active, must_change_password, created_at)
             VALUES (:username, :password_hash, :full_name, :role, 1, 1, :created_at)'
        )->execute([
            'username'      => 'admin',
            'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
            'full_name'     => 'Amministratore',
            'role'          => 'admin',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Categorie iniziali suggerite, modificabili da admin e manutentori.
     */
    private static function seedDefaultCategories(PDO $pdo): void
    {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

        if ($total > 0) {
            return;
        }

        $statement = $pdo->prepare('INSERT INTO categories (name, is_active, created_at) VALUES (:name, 1, :created_at)');

        foreach (['Utensile', 'Macchinario', 'Linea di produzione', 'Utilità'] as $name) {
            $statement->execute(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
}
