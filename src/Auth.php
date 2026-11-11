<?php

declare(strict_types=1);

/**
 * Autenticazione basata su sessione PHP.
 * Non esiste registrazione: gli utenti li crea solo l'amministratore.
 */
final class Auth
{
    private const SESSION_KEY = 'user_id';

    /**
     * Hash fittizio usato per mantenere costante il tempo di risposta
     * quando lo username non esiste (evita di rivelare quali utenti esistono).
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

    /**
     * @param array<string, mixed> $config
     */
    public static function startSession(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) ($config['name'] ?? 'manutenzioni_session'));

        session_set_cookie_params([
            'lifetime' => (int) ($config['lifetime'] ?? 0),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);

        session_start();
    }

    /**
     * Verifica le credenziali e autentica l'utente.
     */
    public static function attempt(string $username, string $password): bool
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return false;
        }

        $statement = Database::pdo()->prepare(
            'SELECT id, username, password_hash, full_name, role, is_active, must_change_password, theme, last_login_at
             FROM users
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch() ?: null;

        if ($user === null || (int) $user['is_active'] !== 1) {
            // Consuma comunque un tempo di verifica analogo.
            password_verify($password, self::DUMMY_HASH);

            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::pdo()
                ->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }

        // Nuovo ID di sessione ad ogni login (protezione da session fixation).
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = (int) $user['id'];
        $_SESSION['previous_login_at'] = $user['last_login_at'];

        Database::pdo()
            ->prepare('UPDATE users SET last_login_at = :now WHERE id = :id')
            ->execute(['now' => date('Y-m-d H:i:s'), 'id' => $user['id']]);

        self::$user = $user;
        self::$resolved = true;

        return true;
    }

    /**
     * Utente autenticato corrente, oppure null.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;

        $id = $_SESSION[self::SESSION_KEY] ?? null;

        if ($id === null || !is_numeric($id)) {
            return self::$user = null;
        }

        $statement = Database::pdo()->prepare(
            'SELECT id, username, full_name, role, is_active, must_change_password, theme, created_at, last_login_at
             FROM users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => (int) $id]);
        $user = $statement->fetch() ?: null;

        if ($user === null) {
            self::logout();

            return null;
        }

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStaff(): bool
    {
        return in_array(self::role(), ['admin', 'manutentore'], true);
    }

    public static function verifyCurrentPassword(string $password): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        $statement = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $statement->execute(['id' => (int) $user['id']]);
        $hash = $statement->fetchColumn();

        return is_string($hash) && password_verify($password, $hash);
    }

    public static function updatePassword(int $userId, string $newPassword): void
    {
        Database::pdo()
            ->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id')
            ->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$resolved = true;

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Blocca l'accesso alle pagine riservate.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login.php');
        }
    }

    /**
     * Login + configurazione iniziale completata.
     * Il primo accesso passa sempre dal setup (password e dati azienda).
     */
    public static function requireReady(): void
    {
        self::requireLogin();

        $user = self::user();

        if ($user === null) {
            redirect('login.php');
        }

        if ((int) $user['must_change_password'] === 1 || !Settings::isSetupComplete()) {
            redirect('setup.php');
        }
    }

    /**
     * Solo amministratore e manutentore.
     */
    public static function requireStaff(): void
    {
        self::requireReady();

        if (!self::isStaff()) {
            flash('error', 'Non hai i permessi per questa sezione.');

            redirect('dashboard.php');
        }
    }

    /**
     * Solo amministratore.
     */
    public static function requireAdmin(): void
    {
        self::requireReady();

        if (!self::isAdmin()) {
            flash('error', 'Sezione riservata all\'amministratore.');

            redirect('dashboard.php');
        }
    }
}
