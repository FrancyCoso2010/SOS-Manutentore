<?php

declare(strict_types=1);

/**
 * Impostazioni dell'azienda salvate a database (chiave/valore).
 */
final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = Database::pdo()
            ->query('SELECT setting_key, setting_value FROM settings')
            ->fetchAll();

        $values = [];

        foreach ($rows as $row) {
            $values[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return self::$cache = $values;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function set(string $key, string $value): void
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        $exists = $pdo->prepare('SELECT 1 FROM settings WHERE setting_key = :key LIMIT 1');
        $exists->execute(['key' => $key]);

        if ($exists->fetchColumn() !== false) {
            $pdo->prepare('UPDATE settings SET setting_value = :value, updated_at = :now WHERE setting_key = :key')
                ->execute(['value' => $value, 'now' => $now, 'key' => $key]);
        } else {
            $pdo->prepare('INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:key, :value, :now)')
                ->execute(['key' => $key, 'value' => $value, 'now' => $now]);
        }

        self::$cache = null;
    }

    /**
     * @param array<string, string> $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function isSetupComplete(): bool
    {
        return self::get('setup_completed') === '1' && self::get('company_name') !== '';
    }
}
